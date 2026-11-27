<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\ConsignmentSettlement;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\ConsignmentService;
use App\Modules\Inventory\Services\KitService;
use App\Modules\Inventory\Services\ReservationService;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Services\DropShipService;
use App\Modules\Sales\Models\CoreReturn;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\CoreChargeService;
use App\Support\Options;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Stock reservations, kits, consignment stock, core returns and drop shipping. */
class StockOpsController extends Controller
{
    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    protected function inTenant(string $table): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists($table, 'id')->where('company_id', tenant()->id());
    }

    /* ---------------- reservations ---------------- */

    public function reservations(Request $request)
    {
        $this->can('stock.view');
        $status = in_array($request->query('status'), ['active', 'released', 'consumed', 'expired'], true) ? $request->query('status') : 'active';

        return view('inventory::reservations', ['status' => $status, 'warehouses' => Options::warehouses(),
            'rows' => StockReservation::query()->with('part', 'warehouse', 'customer', 'jobCard', 'salesOrder', 'user')->where('status', $status)->latest('id')->paginate(30)->withQueryString()]);
    }

    public function storeReservation(Request $request, ReservationService $svc)
    {
        $this->can('stock.update');
        $data = $request->validate([
            'part_id' => ['required', 'integer', $this->inTenant('parts')], 'warehouse_id' => ['required', 'integer', $this->inTenant('warehouses')],
            'qty' => ['required', 'numeric', 'gt:0'], 'customer_id' => ['nullable', 'integer', $this->inTenant('customers')],
            'sales_order_id' => ['nullable', 'integer', $this->inTenant('sales_orders')],
            'job_card_id' => ['nullable', 'integer', $this->inTenant('job_cards')], 'reason' => ['nullable', 'string', 'max:190'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $r = $svc->reserve($data);

        return back()->with('success', __('Reservation :n saved.', ['n' => $r->number]));
    }

    public function releaseReservation(Request $request, StockReservation $reservation, ReservationService $svc)
    {
        $this->can('stock.update');
        $svc->release($reservation, $request->input('as') === 'consumed' ? 'consumed' : 'released');

        return back()->with('success', __('Reservation released.'));
    }

    /* ---------------- kits ---------------- */

    public function kitComponents(Request $request, Part $part, KitService $kits)
    {
        $this->can('parts.update');
        $data = $request->validate(['components' => ['nullable', 'array', 'max:40'], 'components.*.component_part_id' => ['nullable', 'integer', $this->inTenant('parts')],
            'components.*.qty' => ['nullable', 'numeric', 'min:0']]);
        $kits->setComponents($part, $data['components'] ?? []);

        return back()->with('success', __('Kit components saved.'));
    }

    public function kitBuild(Request $request, Part $part, string $action, KitService $kits)
    {
        $this->can('stock.update');
        $data = $request->validate(['warehouse_id' => ['required', 'integer', $this->inTenant('warehouses')], 'qty' => ['required', 'integer', 'min:1', 'max:10000']]);
        $action === 'assemble' ? $kits->assemble($part, (int) $data['warehouse_id'], $data['qty']) : $kits->disassemble($part, (int) $data['warehouse_id'], $data['qty']);

        return back()->with('success', $action === 'assemble' ? __(':n kits assembled.', ['n' => $data['qty']]) : __(':n kits taken apart.', ['n' => $data['qty']]));
    }

    /* ---------------- consignment ---------------- */

    public function consignment(ConsignmentService $svc)
    {
        $this->can('stock.view');
        $whs = Warehouse::query()->with('consignor')->where('is_consignment', true)->get();

        return view('inventory::consignment', ['warehouses' => $whs->map(fn ($w) => [
            'w' => $w, 'value' => (float) \Illuminate\Support\Facades\DB::table('stock_levels')->where('warehouse_id', $w->id)->sum(\Illuminate\Support\Facades\DB::raw('qty_on_hand * avg_cost')),
            'sold' => collect($svc->soldSince($w))->sum(fn ($s) => (float) $s['cost']), 'last' => $svc->lastSettlementAt($w),
        ])]);
    }

    public function consignmentWarehouse(Warehouse $warehouse, ConsignmentService $svc)
    {
        $this->can('stock.view');
        abort_unless($warehouse->is_consignment, 404);

        return view('inventory::consignment-warehouse', ['w' => $warehouse->load('consignor'), 'sold' => $svc->soldSince($warehouse), 'last' => $svc->lastSettlementAt($warehouse),
            'stock' => \App\Modules\Inventory\Models\StockLevel::query()->with('part')->where('warehouse_id', $warehouse->id)->where('qty_on_hand', '>', 0)->get(),
            'settlements' => ConsignmentSettlement::query()->with('supplierInvoice')->where('warehouse_id', $warehouse->id)->latest('id')->get()]);
    }

    public function consignmentAction(Request $request, Warehouse $warehouse, string $action, ConsignmentService $svc)
    {
        abort_unless($warehouse->is_consignment, 404);
        if ($action === 'settle') {
            $this->can('supplier_invoices.create');
            $data = $request->validate(['supplier_invoice_number' => ['nullable', 'string', 'max:60']]);
            $s = $svc->settle($warehouse, $data['supplier_invoice_number'] ?? null);

            return redirect()->route('supplier-invoices.show', $s->supplier_invoice_id)->with('success', __('Consignment settled: draft supplier invoice created.'));
        }
        $this->can('stock.update');
        $data = $request->validate(['lines' => ['required', 'array', 'max:50'], 'lines.*.part_id' => ['required', 'integer', $this->inTenant('parts')],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'], 'lines.*.unit_cost' => [$action === 'receive' ? 'required' : 'nullable', 'numeric', 'gt:0'], 'reference' => ['nullable', 'string', 'max:60']]);
        $lines = $data['lines'];
        $action === 'receive' ? $svc->receive($warehouse, $lines, $data['reference'] ?? null) : $svc->returnToConsignor($warehouse, $lines);

        return back()->with('success', $action === 'receive' ? __('Consignment stock received.') : __('Parts returned to the consignor.'));
    }

    /* ---------------- core returns ---------------- */

    public function cores(Request $request)
    {
        $this->can('invoices.view');
        $status = in_array($request->query('status'), ['pending', 'returned', 'forfeited'], true) ? $request->query('status') : 'pending';

        return view('inventory::cores', ['status' => $status,
            'rows' => CoreReturn::query()->with('invoice', 'customer', 'part')->where('status', $status)->orderBy('due_date')->paginate(30)->withQueryString(),
            'pendingTotal' => CoreReturn::query()->where('status', 'pending')->sum('amount')]);
    }

    public function coreAction(Request $request, CoreReturn $core, string $action, CoreChargeService $svc)
    {
        $this->can('credit_notes.create');
        if ($action === 'receive') {
            $this->can('credit_notes.approve');
        }
        $data = $request->validate(['condition' => ['nullable', 'in:good,damaged'], 'notes' => ['nullable', 'string', 'max:190']]);
        $action === 'receive' ? $svc->receive($core, $data['condition'] ?? 'good', $data['notes'] ?? null) : $svc->forfeit($core, $data['notes'] ?? null);

        return back()->with('success', $action === 'receive' ? __('Core received and the deposit refunded with a credit note.') : __('Core deposit kept.'));
    }

    /* ---------------- drop shipping ---------------- */

    public function dropShip(Request $request, SalesOrder $order, DropShipService $svc)
    {
        $this->can('sales_orders.update');
        $this->can('purchase_orders.create');
        $po = $svc->createPo($order->load('items.part', 'customer'), (int) $request->validate(['supplier_id' => ['required', 'integer', $this->inTenant('suppliers')]])['supplier_id']);

        return redirect()->route('purchase-orders.show', $po)->with('success', __('Drop-ship purchase order :n created.', ['n' => $po->number]));
    }

    public function dropShipDelivered(PurchaseOrder $po, DropShipService $svc)
    {
        $this->can('goods_receipts.create');
        $this->can('invoices.create');
        $inv = $svc->delivered($po->load('items'));

        return redirect()->route('invoices.show', $inv)->with('success', __('Delivery confirmed: the customer invoice is ready to post.'));
    }
}
