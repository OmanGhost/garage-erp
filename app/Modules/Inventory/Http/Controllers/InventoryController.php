<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\PartCategory;
use App\Modules\Inventory\Models\PartCompatibility;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Services\InventoryDocuments;
use App\Modules\Inventory\Services\PartSearch;
use App\Modules\Inventory\Services\ReorderService;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Vehicles\Models\VehicleMake;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(protected InventoryDocuments $docs) {}

    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    public function search(Request $request)
    {
        $this->can('parts.view');
        $filters = $request->only(['q', 'part_type', 'condition_grade', 'brand_id', 'part_category_id', 'make_id', 'model_id', 'year', 'vin', 'in_stock', 'warehouse_id']);
        $parts = collect($filters)->filter()->isNotEmpty() ? PartSearch::query($filters)->paginate(30)->withQueryString() : null;

        return view('inventory::search', [
            'parts' => $parts, 'f' => $filters, 'brands' => Brand::query()->orderBy('name')->pluck('name', 'id'),
            'categories' => PartCategory::query()->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()]),
            'makes' => VehicleMake::query()->orderBy('name')->pluck('name', 'id'), 'warehouses' => Options::warehouses(),
        ]);
    }

    public function part(Part $part)
    {
        $this->can('parts.view');
        $part->load('kitItems.component', 'brand', 'category', 'supplier', 'alternatives', 'compatibilities.make', 'compatibilities.vehicleModel', 'stockLevels.warehouse', 'stockLevels.location', 'suppliers');
        $movements = $part->movements()->with('warehouse', 'user')->latest('moved_at')->limit(50)->get();
        $batches = $part->batches()->with('warehouse')->where('qty_remaining', '>', 0)->orderBy('received_at')->get();
        $sales = DB::table('invoice_items as i')->join('invoices as v', 'v.id', '=', 'i.invoice_id')->where('v.company_id', tenant()->id())
            ->where('i.part_id', $part->id)->whereIn('v.status', ['posted', 'partially_paid', 'paid'])
            ->selectRaw('COALESCE(SUM(i.qty - i.returned_qty),0) qty, COALESCE(SUM(i.line_total),0) revenue, COALESCE(SUM(i.cost_total),0) cost')->first();
        $forecast = app(ReorderService::class);
        $history = $forecast->monthlyConsumption($part->id);

        return view('inventory::part', ['p' => $part, 'warehouses' => Options::warehouses(), 'movements' => $movements, 'batches' => $batches, 'sales' => $sales,
            'history' => $history, 'forecast' => $forecast->forecast($history), 'makes' => VehicleMake::query()->orderBy('name')->pluck('name', 'id'),
            'group' => app(\App\Modules\Inventory\Services\CrossReferenceService::class)->group($part),
            'prices' => app(\App\Modules\Inventory\Services\InventoryAnalyticsService::class)->priceHistory($part->id)]);
    }

    /* ---------------- cross reference ---------------- */

    public function crossReference(Request $request)
    {
        $this->can('parts.view');
        $q = trim((string) $request->query('q'));
        $xref = app(\App\Modules\Inventory\Services\CrossReferenceService::class);
        $parts = $q !== '' ? $xref->search($q) : collect();

        return view('inventory::cross-reference', ['q' => $q, 'parts' => $parts, 'numbers' => $parts->isNotEmpty() ? $xref->numbers($parts) : collect()]);
    }

    public function addAlternative(Request $request, Part $part)
    {
        $this->can('parts.update');
        $data = $request->validate([
            'alternative_part_id' => ['nullable', 'integer', Rule::exists('parts', 'id')->where('company_id', tenant()->id())],
            'number' => ['nullable', 'string', 'max:80'], 'brand' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:alternative,superseded,cross_reference'], 'quality' => ['nullable', Rule::in(\App\Modules\Inventory\Services\CrossReferenceService::QUALITIES)],
            'notes' => ['nullable', 'string', 'max:190'],
        ]);
        app(\App\Modules\Inventory\Services\CrossReferenceService::class)->link($part, $data);

        return back()->with('success', __('Alternative linked.'));
    }

    public function deleteAlternative(\App\Modules\Inventory\Models\PartAlternative $alternative)
    {
        $this->can('parts.update');
        abort_unless(Part::query()->whereKey($alternative->part_id)->exists(), 404);
        $alternative->delete();

        return back()->with('success', __('Alternative removed.'));
    }

    /* ---------------- analytics & automation ---------------- */

    public function analytics(Request $request)
    {
        $this->can('stock.view');
        $svc = app(\App\Modules\Inventory\Services\InventoryAnalyticsService::class);
        $tab = in_array($request->query('tab'), ['abc', 'aging', 'dead', 'forecast', 'cycle'], true) ? $request->query('tab') : 'abc';
        $wh = $request->integer('warehouse_id') ?: (int) (setting('inventory.auto_reorder_warehouse') ?: \App\Modules\Inventory\Models\Warehouse::query()->value('id'));
        $abc = $tab === 'abc' ? $svc->abc(12, false) : null;

        return view('inventory::analytics', [
            'tab' => $tab, 'warehouseId' => $wh, 'warehouses' => Options::warehouses(),
            'abc' => $abc, 'abcParts' => $abc ? Part::query()->whereIn('id', array_slice(array_keys($abc['rows']), 0, 100))->get()->keyBy('id') : collect(),
            'aging' => $tab === 'aging' ? $svc->aging($request->integer('warehouse_id') ?: null) : null,
            'dead' => $tab === 'dead' ? app(ReorderService::class)->slowMoving((int) $request->query('days', 180))->limit(200)->get() : null,
            'forecasts' => $tab === 'forecast' ? $svc->forecasts() : null,
            'due' => $tab === 'cycle' ? $svc->dueForCount($wh, 200) : null,
            'lastAbc' => Part::query()->max('abc_calculated_at'),
        ]);
    }

    public function analyticsAction(Request $request, string $action)
    {
        $svc = app(\App\Modules\Inventory\Services\InventoryAnalyticsService::class);
        $wh = (int) $request->validate(['warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('company_id', tenant()->id())]])['warehouse_id']
            ?: (int) \App\Modules\Inventory\Models\Warehouse::query()->value('id');
        switch ($action) {
            case 'abc':
                $this->can('parts.update');
                $r = $svc->abc();

                return back()->with('success', __('ABC classes updated: A :a, B :b, C :c parts.', ['a' => $r['summary']['A']['parts'], 'b' => $r['summary']['B']['parts'], 'c' => $r['summary']['C']['parts']]));
            case 'reorder':
                $this->can('purchase_orders.create');
                $orders = $svc->autoReorder($wh);

                return back()->with($orders ? 'success' : 'info', $orders ? __(':n draft purchase orders created.', ['n' => count($orders)]) : __('Nothing to reorder: stock is sufficient or already on order.'));
            case 'cycle':
                $this->can('stock_counts.create');
                $count = $svc->createCycleCount($wh, max(5, min(500, $request->integer('limit', 50))));

                return $count ? redirect()->route('inventory.counts.show', $count)->with('success', __('Cycle count created.'))
                    : back()->with('info', __('No parts are due for counting in this warehouse.'));
            case 'settings':
                $this->can('settings.update');
                \App\Support\Settings::set(['inventory.auto_reorder' => $request->boolean('auto_reorder'), 'inventory.auto_reorder_warehouse' => $wh]);

                return back()->with('success', __('Saved.'));
        }
        abort(404);
    }

    public function addCompatibility(Request $request, Part $part)
    {
        $this->can('parts.update');
        $data = $request->validate(['vehicle_make_id' => ['nullable', 'exists:vehicle_makes,id'], 'vehicle_model_id' => ['nullable', 'exists:vehicle_models,id'],
            'year_from' => ['nullable', 'integer', 'min:1950'], 'year_to' => ['nullable', 'integer', 'gte:year_from'], 'engine' => ['nullable', 'string', 'max:60'],
            'vin_pattern' => ['nullable', 'string', 'max:17', 'alpha_num'], 'notes' => ['nullable', 'string', 'max:250']]);
        abort_if(! array_filter($data), 422);
        if (! empty($data['vin_pattern'])) {
            $data['vin_pattern'] = strtoupper($data['vin_pattern']);
        }
        $part->compatibilities()->create($data);

        return back()->with('success', __('Compatibility added.'));
    }

    public function deleteCompatibility(PartCompatibility $compat)
    {
        $this->can('parts.update');
        Part::query()->findOrFail($compat->part_id); // tenant check
        $compat->delete();

        return back();
    }

    /** Barcode / QR label sheet for selected parts (or a single part). */
    public function labels(Request $request)
    {
        $this->can('parts.print');
        $ids = array_filter(array_map('intval', (array) $request->query('ids', [])));
        $parts = Part::query()->whereIn('id', $ids ?: [0])->get();
        $copies = max(1, min(100, (int) $request->query('copies', 1)));

        return view('inventory::labels', compact('parts', 'copies'));
    }

    public function importForm()
    {
        $this->can('parts.create');

        return view('inventory::import');
    }

    /** CSV import: sku,name_ar,name_en,oem_number,barcode,brand,category,part_type,purchase_price,sale_price,min_stock */
    public function import(Request $request)
    {
        $this->can('parts.create');
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $fh = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h))), fgetcsv($fh) ?: []);
        $created = $updated = $skipped = 0;
        DB::transaction(function () use ($fh, $header, &$created, &$updated, &$skipped) {
            while (($row = fgetcsv($fh)) !== false) {
                $r = @array_combine($header, array_pad($row, count($header), null));
                if (! $r || empty($r['name_ar'] ?? $r['name'] ?? null)) {
                    $skipped++;
                    continue;
                }
                $brand = ! empty($r['brand']) ? Brand::query()->firstOrCreate(['name' => trim($r['brand'])]) : null;
                $cat = ! empty($r['category']) ? PartCategory::query()->firstOrCreate(['name_ar' => trim($r['category'])]) : null;
                $data = array_filter([
                    'name_ar' => $r['name_ar'] ?? $r['name'], 'name_en' => $r['name_en'] ?? null, 'oem_number' => $r['oem_number'] ?? null,
                    'barcode' => $r['barcode'] ?? null, 'brand_id' => $brand?->id, 'part_category_id' => $cat?->id,
                    'part_type' => in_array($r['part_type'] ?? '', Part::TYPES, true) ? $r['part_type'] : null,
                    'purchase_price' => is_numeric($r['purchase_price'] ?? null) ? $r['purchase_price'] : null,
                    'sale_price' => is_numeric($r['sale_price'] ?? null) ? $r['sale_price'] : null,
                    'min_stock' => is_numeric($r['min_stock'] ?? null) ? $r['min_stock'] : null,
                ], fn ($v) => $v !== null && $v !== '');
                $sku = trim((string) ($r['sku'] ?? ''));
                $part = $sku ? Part::query()->where('sku', $sku)->first() : null;
                if ($part) {
                    $part->update($data);
                    $updated++;
                } else {
                    Part::create($data + ['sku' => $sku ?: DocumentNumber::next('part', false)]);
                    $created++;
                }
            }
        });
        fclose($fh);

        return redirect()->route('crud.index', 'parts')->with('success', __('Import finished: :c created, :u updated, :s skipped.', ['c' => $created, 'u' => $updated, 's' => $skipped]));
    }

    public function reorder(Request $request)
    {
        $this->can('stock.view');

        return view('inventory::reorder', ['rows' => app(ReorderService::class)->suggestions($request->integer('warehouse_id') ?: null),
            'warehouses' => Options::warehouses(), 'slow' => app(ReorderService::class)->slowMoving((int) $request->query('days', 180))->limit(50)->get()]);
    }

    /** Turn selected suggestions into draft purchase orders grouped by default supplier. */
    public function createOrdersFromSuggestions(Request $request, PurchasingService $purchasing)
    {
        $this->can('purchase_orders.create');
        $data = $request->validate(['qty' => ['required', 'array'], 'qty.*' => ['nullable', 'numeric', 'min:0'], 'warehouse_id' => ['required', 'integer']]);
        $groups = [];
        foreach (Part::query()->whereIn('id', array_keys(array_filter($data['qty'])))->get() as $p) {
            if (! $p->default_supplier_id) {
                continue;
            }
            $groups[$p->default_supplier_id][] = ['part_id' => $p->id, 'qty' => $data['qty'][$p->id], 'unit_cost' => $p->purchase_price];
        }
        $n = 0;
        foreach ($groups as $supplierId => $items) {
            $purchasing->createOrder(['supplier_id' => $supplierId, 'warehouse_id' => $data['warehouse_id'], 'notes' => __('Auto-generated from reorder suggestions')], $items);
            $n++;
        }

        return redirect()->route('crud.index', 'purchase-orders')->with($n ? 'success' : 'warning', $n
            ? __(':n draft purchase orders created.', ['n' => $n]) : __('Selected parts have no default supplier.'));
    }

    // ------------------------------------------------------------- transfers

    public function createTransfer()
    {
        $this->can('stock_transfers.create');

        return view('inventory::transfer-form', ['warehouses' => Options::warehouses()]);
    }

    public function storeTransfer(Request $request)
    {
        $this->can('stock_transfers.create');
        $c = tenant()->id();
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $c)],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id', Rule::exists('warehouses', 'id')->where('company_id', $c)],
            'transfer_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'], 'items.*.part_id' => ['nullable', 'integer', Rule::exists('parts', 'id')->where('company_id', $c)],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
        ]);
        $items = array_filter($data['items'], fn ($i) => ! empty($i['part_id']) && Money::isPositive($i['qty'] ?? 0));
        $t = $this->docs->createTransfer(collect($data)->except('items')->all(), $items);
        if ($request->boolean('send_now')) {
            $this->docs->sendTransfer($t);
        }

        return redirect()->route('inventory.transfers.show', $t)->with('success', __('Transfer saved.'));
    }

    public function transfer(StockTransfer $transfer)
    {
        $this->can('stock_transfers.view');

        return view('inventory::transfer', ['t' => $transfer->load('items.part', 'fromWarehouse', 'toWarehouse', 'creator', 'sender', 'receiver', 'canceller', 'events.user')]);
    }

    public function transferAction(Request $request, StockTransfer $transfer, string $action)
    {
        $this->can('stock_transfers.update');
        match ($action) {
            'send' => $this->docs->sendTransfer($transfer),
            'receive' => $this->docs->receiveTransfer($transfer, (array) $request->input('received', []), mb_substr((string) $request->input('notes'), 0, 500) ?: null),
            'cancel' => $this->docs->cancelTransfer($transfer, mb_substr((string) $request->input('reason'), 0, 250) ?: null),
        };

        return back()->with('success', __('Transfer updated.'));
    }

    public function transferNote(Request $request, StockTransfer $transfer)
    {
        $this->can('stock_transfers.update');
        $note = $request->validate(['note' => ['required', 'string', 'max:500']])['note'];
        $transfer->logEvent('note', $note);

        return back()->with('success', __('Note added.'));
    }

    /** $type: request (before dispatch) | completed (sent vs received). */
    public function printTransfer(StockTransfer $transfer, string $type = 'request', string $format = 'a4')
    {
        $this->can('stock_transfers.print');
        $transfer->load('items.part', 'fromWarehouse', 'toWarehouse', 'creator', 'sender', 'receiver', 'events.user');
        $html = view('inventory::print.transfer', ['t' => $transfer, 'type' => $type])->render();
        if ($format === 'pdf') {
            return response(\App\Support\Export\Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$transfer->number.'-'.$type.'.pdf"']);
        }

        return response($html);
    }

    // ---------------------------------------------------------------- counts

    public function createCount()
    {
        $this->can('stock_counts.create');

        return view('inventory::count-form', ['warehouses' => Options::warehouses(),
            'categories' => PartCategory::query()->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])]);
    }

    public function storeCount(Request $request)
    {
        $this->can('stock_counts.create');
        $data = $request->validate(['warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', tenant()->id())],
            'type' => ['required', 'in:periodic,annual,cycle'], 'part_category_id' => ['nullable', 'integer'], 'notes' => ['nullable', 'string', 'max:500']]);
        $partIds = ! empty($data['part_category_id']) ? Part::query()->where('part_category_id', $data['part_category_id'])->pluck('id')->all() : null;
        $count = $this->docs->createCount($data['warehouse_id'], $data['type'], $partIds, $data['notes'] ?? null);

        return redirect()->route('inventory.counts.show', $count);
    }

    public function count(StockCount $count)
    {
        $this->can('stock_counts.view');

        return view('inventory::count', ['c' => $count->load('items.part', 'warehouse')]);
    }

    public function saveCount(Request $request, StockCount $count)
    {
        $this->can('stock_counts.update');
        abort_if($count->status === 'posted', 403);
        $data = $request->validate(['counted' => ['array'], 'counted.*' => ['nullable', 'numeric', 'min:0']]);
        foreach ($count->items as $item) {
            if (array_key_exists($item->id, $data['counted'] ?? [])) {
                $v = $data['counted'][$item->id];
                $item->update(['counted_qty' => $v === null || $v === '' ? null : $v, 'difference' => $v === null || $v === '' ? 0 : Money::sub($v, $item->system_qty)]);
            }
        }

        return back()->with('success', __('Counts saved.'));
    }

    public function postCount(StockCount $count)
    {
        $this->can('stock_counts.approve');
        $this->docs->postCount($count);

        return back()->with('success', __('Stock count posted. Differences were adjusted and posted to accounts.'));
    }

    // ----------------------------------------------------------- adjustments

    public function createAdjustment()
    {
        $this->can('stock_adjustments.create');

        return view('inventory::adjustment-form', ['warehouses' => Options::warehouses()]);
    }

    public function storeAdjustment(Request $request)
    {
        $this->can('stock_adjustments.create');
        $c = tenant()->id();
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $c)],
            'date' => ['required', 'date'], 'reason' => ['required', 'in:damage,loss,found,correction,expired,opening'], 'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array'], 'items.*.part_id' => ['nullable', 'integer', Rule::exists('parts', 'id')->where('company_id', $c)],
            'items.*.qty' => ['nullable', 'numeric'], 'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'], 'items.*.notes' => ['nullable', 'string', 'max:250'],
        ]);
        $adj = DB::transaction(function () use ($data) {
            $a = StockAdjustment::create(collect($data)->except('items')->all() + ['number' => DocumentNumber::next('stock_adjustment'), 'status' => 'draft', 'created_by' => auth()->id()]);
            foreach ($data['items'] as $i) {
                if (! empty($i['part_id']) && ! Money::isZero($i['qty'] ?? 0)) {
                    $qty = in_array($data['reason'], ['damage', 'loss', 'expired'], true) ? Money::neg(abs((float) $i['qty'])) : $i['qty'];
                    $a->items()->create(['part_id' => $i['part_id'], 'qty' => $qty, 'unit_cost' => $i['unit_cost'] ?? 0, 'notes' => $i['notes'] ?? null]);
                }
            }

            return $a;
        });
        if ($request->boolean('post_now') && auth()->user()->can('stock_adjustments.approve')) {
            $this->docs->postAdjustment($adj);
        }

        return redirect()->route('inventory.adjustments.show', $adj)->with('success', __('Adjustment saved.'));
    }

    public function adjustment(StockAdjustment $adjustment)
    {
        $this->can('stock_adjustments.view');

        return view('inventory::adjustment', ['a' => $adjustment->load('items.part', 'warehouse', 'creator')]);
    }

    public function postAdjustment(StockAdjustment $adjustment)
    {
        $this->can('stock_adjustments.approve');
        $this->docs->postAdjustment($adjustment);

        return back()->with('success', __('Adjustment posted.'));
    }
}
