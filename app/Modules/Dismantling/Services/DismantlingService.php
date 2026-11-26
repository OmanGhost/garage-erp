<?php

namespace App\Modules\Dismantling\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Dismantling\Models\DismantledPart;
use App\Modules\Dismantling\Models\DismantledScrap;
use App\Modules\Dismantling\Models\DismantledVehicle;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Services\StockService;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Used / damaged vehicle purchasing & dismantling:
 *   purchase:   Dr Vehicles for dismantling   Cr Cash/Bank or AP
 *   extraction: Dr Inventory (allocated cost) Cr Vehicles for dismantling
 *   scrap sale: Dr Cash                      Cr Scrap revenue (+ write-off of unallocated cost)
 * Vehicle cost is allocated to extracted parts proportionally to their estimated value.
 * Every extracted part gets its own serial number & barcode and keeps its source vehicle.
 */
class DismantlingService
{
    public function __construct(protected StockService $stock, protected PostingService $posting) {}

    public function purchase(array $data): DismantledVehicle
    {
        return DB::transaction(function () use ($data) {
            $v = DismantledVehicle::create($data + ['number' => DocumentNumber::next('dismantled_vehicle'), 'status' => 'purchased', 'created_by' => auth()->id()]);
            $this->postCosts($v, $v->totalCost(), 'purchase');

            return $v;
        });
    }

    /** Post the purchase of a vehicle created through the generic form. */
    public function registerPurchase(DismantledVehicle $v): void
    {
        $this->postCosts($v, $v->totalCost(), 'purchase');
    }

    /** Add extra cost (transport / dismantling labor / other) after purchase. */
    public function addCost(DismantledVehicle $v, string $type, mixed $amount, ?int $bankAccountId = null): void
    {
        if (! in_array($type, ['transport_cost', 'dismantling_cost', 'other_costs'], true)) {
            throw new BusinessException(__('Invalid cost type.'));
        }
        DB::transaction(function () use ($v, $type, $amount, $bankAccountId) {
            $v->update([$type => Money::add($v->{$type}, $amount)]);
            $this->postCosts($v, Money::round($amount), $type.':'.now()->format('YmdHisv'), $bankAccountId);
        });
    }

    protected function postCosts(DismantledVehicle $v, string $amount, string $purpose, ?int $bankAccountId = null): void
    {
        if (! Money::isPositive($amount)) {
            return;
        }
        $bankId = $bankAccountId ?? $v->paid_from_bank_account_id;
        $credit = $bankId
            ? ['account' => BankAccount::query()->findOrFail($bankId)->account_id, 'credit' => $amount]
            : ['account' => 'ap', 'credit' => $amount, 'party' => $v->supplier];
        if (! $bankId && ! $v->supplier_id) {
            throw new BusinessException(__('Select a supplier or the cash/bank account used to pay.'));
        }
        $this->posting->post([
            'date' => now()->toDateString(), 'description' => __('Dismantling vehicle').' '.$v->number.' '.$v->vin,
            'source' => $v, 'purpose' => $purpose,
            'lines' => [['account' => 'dismantling', 'debit' => $amount], $credit],
        ]);
    }

    /** Register an extracted part (pending until moved to stock). */
    public function addPart(DismantledVehicle $v, array $data): DismantledPart
    {
        if ($v->status === 'closed') {
            throw new BusinessException(__('This vehicle file is closed.'));
        }
        if ($v->status === 'purchased') {
            $v->update(['status' => 'dismantling']);
        }
        $serial = $data['serial_number'] ?? DocumentNumber::next('serial', false);

        return $v->parts()->create([
            'part_id' => $data['part_id'] ?? null, 'name' => $data['name'], 'template_key' => $data['template_key'] ?? null, 'oem_number' => $data['oem_number'] ?? null,
            'grade' => $data['grade'] ?? 'A', 'estimated_value' => $data['estimated_value'] ?? 0, 'sale_price' => $data['sale_price'] ?? ($data['estimated_value'] ?? 0),
            'serial_number' => $serial, 'barcode' => $data['barcode'] ?? $serial,
            'warehouse_id' => $data['warehouse_id'] ?? $v->warehouse_id, 'location_id' => $data['location_id'] ?? null,
            'status' => 'pending', 'notes' => $data['notes'] ?? null,
        ]);
    }

    /** Validation for the checklist inputs (new-vehicle form and dismantling file). */
    public static function checklistRules(): array
    {
        return [
            'checklist' => ['nullable', 'array', 'max:120'],
            'checklist.*.selected' => ['nullable', 'boolean'],
            'checklist.*.name' => ['nullable', 'string', 'max:190'],
            'checklist.*.oem' => ['nullable', 'string', 'max:80'],
            'checklist.*.grade' => ['nullable', 'in:A,B,C,damaged'],
            'checklist.*.estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'checklist.*.sale_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'checklist_to_stock' => ['nullable', 'boolean'],
        ];
    }

    /** The standard parts checklist (config/dismantling.php) with labels in the current language. */
    public static function checklist(): array
    {
        $ar = app()->getLocale() === 'ar';
        $out = [];
        foreach (config('dismantling.groups', []) as $gk => $g) {
            $parts = [];
            foreach ($g['parts'] as $pk => [$nameAr, $nameEn]) {
                $parts[$pk] = ['key' => $pk, 'name' => $ar ? $nameAr : $nameEn, 'alt' => $ar ? $nameEn : $nameAr];
            }
            $out[$gk] = ['key' => $gk, 'label' => $ar ? $g['ar'] : $g['en'], 'icon' => $g['icon'] ?? 'bi-gear', 'parts' => $parts];
        }

        return $out;
    }

    /**
     * Factory part numbers for a make / model / year, per checklist key: first from parts already extracted from
     * the same vehicle (model, ±3 years), then from catalog parts listed as compatible with it.
     *
     * @return array<string, array{oem: string, source: string, sale_price: ?string, name: ?string}>
     */
    public function suggestions(?int $makeId, ?int $modelId = null, ?int $year = null): array
    {
        if (! $makeId) {
            return [];
        }
        $out = [];
        $history = DismantledPart::query()->join('dismantled_vehicles as v', 'v.id', '=', 'dismantled_parts.dismantled_vehicle_id')
            ->where('v.vehicle_make_id', $makeId)->when($modelId, fn ($q) => $q->where('v.vehicle_model_id', $modelId))
            ->when($year, fn ($q) => $q->whereBetween('v.year', [$year - 3, $year + 3]))
            ->whereNotNull('dismantled_parts.template_key')->whereNotNull('dismantled_parts.oem_number')->where('dismantled_parts.oem_number', '!=', '')
            ->orderByDesc('dismantled_parts.id')->limit(1000)
            ->get(['dismantled_parts.template_key', 'dismantled_parts.oem_number', 'dismantled_parts.sale_price']);
        foreach ($history as $h) {
            $out[$h->template_key] ??= ['oem' => $h->oem_number, 'source' => 'history', 'sale_price' => (string) $h->sale_price, 'name' => null];
        }
        $catalog = Part::query()->whereNotNull('oem_number')->where('oem_number', '!=', '')
            ->whereHas('compatibilities', fn ($c) => $c->where('vehicle_make_id', $makeId)
                ->when($modelId, fn ($q) => $q->where(fn ($w) => $w->whereNull('vehicle_model_id')->orWhere('vehicle_model_id', $modelId)))
                ->when($year, fn ($q) => $q->where(fn ($w) => $w->whereNull('year_from')->orWhere('year_from', '<=', $year))->where(fn ($w) => $w->whereNull('year_to')->orWhere('year_to', '>=', $year))))
            ->limit(500)->get(['id', 'oem_number', 'name_ar', 'name_en', 'sale_price']);
        foreach (config('dismantling.groups', []) as $g) {
            foreach ($g['parts'] as $key => [$nameAr, $nameEn]) {
                if (isset($out[$key])) {
                    continue;
                }
                $hit = $catalog->first(fn ($p) => ($p->name_ar && (mb_stripos($p->name_ar, $nameAr) !== false || mb_stripos($nameAr, $p->name_ar) !== false))
                    || ($p->name_en && (mb_stripos($p->name_en, $nameEn) !== false || mb_stripos($nameEn, $p->name_en) !== false)));
                if ($hit) {
                    $out[$key] = ['oem' => $hit->oem_number, 'source' => 'catalog', 'sale_price' => null, 'name' => $hit->displayName()];
                }
            }
        }

        return $out;
    }

    /**
     * Register the parts ticked in the checklist.
     *
     * @param  array<string, array{selected?: mixed, name?: ?string, oem?: ?string, grade?: ?string, estimated_value?: mixed, sale_price?: mixed}>  $rows
     * @return array<int, DismantledPart>
     */
    public function addChecklist(DismantledVehicle $v, array $rows, ?int $warehouseId = null): array
    {
        $catalog = collect(self::checklist())->flatMap(fn ($g) => $g['parts']);
        $created = [];
        foreach ($rows as $key => $row) {
            if (empty($row['selected'])) {
                continue;
            }
            $tpl = $catalog->get($key);
            $name = trim((string) ($row['name'] ?? '')) ?: ($tpl['name'] ?? null);
            if (! $name) {
                continue;
            }
            $created[] = $this->addPart($v, [
                'name' => $name, 'template_key' => $tpl ? $key : null, 'oem_number' => trim((string) ($row['oem'] ?? '')) ?: null,
                'grade' => in_array($row['grade'] ?? 'A', ['A', 'B', 'C', 'damaged'], true) ? $row['grade'] : 'A',
                'estimated_value' => Money::round($row['estimated_value'] ?? 0), 'sale_price' => filled($row['sale_price'] ?? null) ? Money::round($row['sale_price']) : null,
                'warehouse_id' => $warehouseId,
            ]);
        }
        if ($created) {
            AuditLogger::log('dismantling.checklist_added', $v, ['parts' => count($created)]);
        }

        return $created;
    }

    /**
     * Allocate remaining vehicle cost over the pending parts (by estimated value)
     * and move them into stock as used parts.
     */
    public function moveToStock(DismantledVehicle $v, bool $final = false): array
    {
        return DB::transaction(function () use ($v, $final) {
            $v = DismantledVehicle::query()->whereKey($v->id)->lockForUpdate()->firstOrFail();
            $pending = $v->parts()->where('status', 'pending')->get();
            if ($pending->isEmpty()) {
                throw new BusinessException(__('There are no pending parts to move to stock.'));
            }
            $remainingCost = Money::sub($v->totalCost(), $v->allocated_cost);
            $stillPendingValue = Money::sum($pending->pluck('estimated_value'));
            if (! Money::isPositive($stillPendingValue)) {
                throw new BusinessException(__('Enter estimated values for the parts to allocate cost.'));
            }
            $closeFile = $final;
            // allocate proportionally; keep a share for future parts unless this is the final batch
            $poolValue = $closeFile ? $stillPendingValue : Money::max($stillPendingValue, Money::sub($this->expectedTotalValue($v), Money::sum($v->parts()->where('status', '!=', 'pending')->pluck('estimated_value'))));
            $allocTotal = '0';
            $moved = [];
            foreach ($pending->values() as $i => $dp) {
                $share = Money::round(bcdiv(bcmul(Money::n($remainingCost), Money::n($dp->estimated_value), 10), Money::n($poolValue), 10));
                if ($closeFile && $i === $pending->count() - 1) {
                    $share = Money::sub($remainingCost, $allocTotal);
                }
                $allocTotal = Money::add($allocTotal, $share);
                $part = $dp->part_id ? Part::query()->find($dp->part_id) : null;
                if (! $part && $dp->oem_number) {
                    // same factory number & grade already stocked as a used part: add this piece to it (own serial)
                    $part = Part::query()->where('oem_number', $dp->oem_number)->where('condition_grade', $dp->grade)
                        ->whereIn('part_type', ['used', 'damaged'])->where('track_serial', true)->first();
                }
                if (! $part) {
                    $part = Part::create([
                        'sku' => 'U-'.$dp->serial_number, 'barcode' => $dp->barcode, 'oem_number' => $dp->oem_number, 'serial_number' => $dp->serial_number,
                        'name_ar' => $dp->name, 'name_en' => $dp->name, 'part_type' => $dp->grade === 'damaged' ? 'damaged' : 'used',
                        'condition_grade' => $dp->grade, 'sale_price' => $dp->sale_price, 'min_sale_price' => 0, 'track_serial' => true,
                        'dismantled_vehicle_id' => $v->id, 'tax_rate' => tenant()->company()?->vat_rate ?? 5,
                    ]);
                    if ($v->vehicle_make_id) {
                        $part->compatibilities()->create(['vehicle_make_id' => $v->vehicle_make_id, 'vehicle_model_id' => $v->vehicle_model_id,
                            'year_from' => $v->year, 'year_to' => $v->year]);
                    }
                }
                $wh = $dp->warehouse_id ?? $v->warehouse_id;
                if (! $wh) {
                    throw new BusinessException(__('Select a warehouse for the extracted parts.'));
                }
                $r = $this->stock->receive($part, $wh, 1, $share, [
                    'type' => 'dismantle_in', 'reference' => $v, 'serials' => [$dp->serial_number], 'dismantled_vehicle_id' => $v->id,
                    'grade' => $dp->grade, 'location_id' => $dp->location_id, 'update_purchase_price' => false,
                ]);
                $dp->update(['part_id' => $part->id, 'allocated_cost' => $share, 'status' => 'in_stock', 'batch_id' => $r['batches'][0] ?? null, 'warehouse_id' => $wh]);
                $moved[] = $dp;
            }
            $this->posting->post([
                'date' => now()->toDateString(), 'description' => __('Parts extracted from').' '.$v->number,
                'source' => $v, 'purpose' => 'extract:'.now()->format('YmdHisv'),
                'lines' => [['account' => 'inventory', 'debit' => $allocTotal], ['account' => 'dismantling', 'credit' => $allocTotal]],
            ]);
            $v->update(['allocated_cost' => Money::add($v->allocated_cost, $allocTotal), 'status' => $closeFile ? 'completed' : 'dismantling']);
            AuditLogger::log('dismantling.moved_to_stock', $v, ['parts' => count($moved), 'allocated' => $allocTotal]);

            return $moved;
        });
    }

    /** Expected total value = all registered parts (pending + moved). */
    protected function expectedTotalValue(DismantledVehicle $v): string
    {
        return Money::sum($v->parts()->where('status', '!=', 'scrapped')->pluck('estimated_value'));
    }

    public function recordScrap(DismantledVehicle $v, array $data): DismantledScrap
    {
        return DB::transaction(function () use ($v, $data) {
            $scrap = $v->scraps()->create($data + ['date' => now()->toDateString()]);
            if (Money::isPositive($scrap->amount) && $scrap->bank_account_id) {
                $je = $this->posting->post([
                    'date' => $scrap->date->toDateString(), 'description' => __('Scrap sale').' '.$v->number, 'source' => $scrap,
                    'lines' => [['account' => $scrap->bankAccount->account_id, 'debit' => $scrap->amount], ['account' => 'scrap_revenue', 'credit' => $scrap->amount]],
                ]);
                $scrap->update(['journal_entry_id' => $je->id]);
            }
            $v->update(['scrap_revenue' => Money::add($v->scrap_revenue, $scrap->amount)]);

            return $scrap;
        });
    }

    /** Close the file: write off any unallocated cost as loss. */
    public function close(DismantledVehicle $v): void
    {
        DB::transaction(function () use ($v) {
            $left = Money::sub($v->totalCost(), $v->allocated_cost);
            if (Money::isPositive($left)) {
                $this->posting->post([
                    'date' => now()->toDateString(), 'description' => __('Unallocated dismantling cost written off').' '.$v->number,
                    'source' => $v, 'purpose' => 'close',
                    'lines' => [['account' => 'inventory_loss', 'debit' => $left], ['account' => 'dismantling', 'credit' => $left]],
                ]);
            }
            $v->update(['status' => 'closed']);
        });
    }

    /** Revenue, cost and profit for one dismantled vehicle (sales traced via cost layers). */
    public function profitability(DismantledVehicle $v): array
    {
        $partIds = $v->parts()->pluck('part_id')->filter()->all();
        $sales = DB::table('invoice_items as i')->join('invoices as inv', 'inv.id', '=', 'i.invoice_id')
            ->where('inv.company_id', tenant()->id())->whereIn('inv.status', ['posted', 'partially_paid', 'paid'])
            ->whereIn('i.part_id', $partIds ?: [0])
            ->selectRaw('COALESCE(SUM(i.line_total - 0),0) revenue, COALESCE(SUM(i.cost_total),0) cost, COALESCE(SUM(i.qty - i.returned_qty),0) qty')->first();
        $sold = DB::table('stock_batches')->where('dismantled_vehicle_id', $v->id)->where('qty_remaining', 0)->count();
        DismantledPart::query()->where('dismantled_vehicle_id', $v->id)->where('status', 'in_stock')
            ->whereIn('batch_id', DB::table('stock_batches')->where('dismantled_vehicle_id', $v->id)->where('qty_remaining', 0)->pluck('id'))
            ->update(['status' => 'sold']);
        $revenue = Money::add($sales->revenue, $v->scrap_revenue);

        return [
            'total_cost' => $v->totalCost(), 'allocated_cost' => (string) $v->allocated_cost,
            'parts_revenue' => Money::round($sales->revenue), 'scrap_revenue' => (string) $v->scrap_revenue,
            'revenue' => $revenue, 'profit' => Money::sub($revenue, $v->totalCost()),
            'parts_total' => $v->parts()->count(), 'parts_sold' => $sold,
            'stock_value' => Money::round(DB::table('stock_batches')->where('dismantled_vehicle_id', $v->id)->sum(DB::raw('qty_remaining * unit_cost'))),
        ];
    }
}
