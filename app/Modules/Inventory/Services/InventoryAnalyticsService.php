<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Inventory analytics and automation: ABC classification by consumption value, stock aging from the cost
 * layers, demand forecast, automatic reordering into draft purchase orders, cycle counting by ABC class and
 * the supplier price history.
 */
class InventoryAnalyticsService
{
    /** Days between counts per ABC class (unclassified parts every 180 days). */
    public const CYCLE_DAYS = ['A' => 30, 'B' => 90, 'C' => 365, '' => 180];

    public function __construct(protected ReorderService $reorder) {}

    /**
     * ABC: consumption value over the last $months months (qty out × average cost), sorted; A = the parts making the
     * first 80 % of the value, B = the next 15 %, C = the rest. Saved on the parts when $store is true.
     */
    public function abc(int $months = 12, bool $store = true): array
    {
        $from = now()->subMonths($months);
        $usage = DB::table('stock_movements')->where('company_id', tenant()->id())->where('moved_at', '>=', $from)
            ->whereIn('type', ['sale', 'job_issue', 'job_return', 'sales_return'])
            ->groupBy('part_id')->selectRaw('part_id, -SUM(qty) qty, -SUM(qty * unit_cost) value')->get()
            ->filter(fn ($r) => (float) $r->value > 0)->sortByDesc(fn ($r) => (float) $r->value)->values();
        $total = (float) $usage->sum('value');
        $rows = [];
        $cum = 0.0;
        foreach ($usage as $r) {
            $cum += (float) $r->value;
            $share = $total > 0 ? $cum * 100 / $total : 100;
            $class = $share <= 80 || ! $rows ? 'A' : ($share <= 95 ? 'B' : 'C');
            $rows[$r->part_id] = ['part_id' => $r->part_id, 'qty' => (float) $r->qty, 'value' => round((float) $r->value, 3), 'cum' => round($share, 1), 'class' => $class];
        }
        if ($store) {
            DB::transaction(function () use ($rows) {
                Part::query()->whereNotNull('abc_class')->update(['abc_class' => null]);
                foreach (['A', 'B', 'C'] as $c) {
                    $ids = array_keys(array_filter($rows, fn ($r) => $r['class'] === $c));
                    foreach (array_chunk($ids, 500) as $chunk) {
                        Part::query()->whereIn('id', $chunk)->update(['abc_class' => $c, 'abc_calculated_at' => now()]);
                    }
                }
                // parts in stock without consumption are C
                Part::query()->whereNull('abc_class')->whereHas('stockLevels', fn ($q) => $q->where('qty_on_hand', '>', 0))->update(['abc_class' => 'C', 'abc_calculated_at' => now()]);
            });
        }
        $summary = [];
        foreach (['A', 'B', 'C'] as $c) {
            $set = array_filter($rows, fn ($r) => $r['class'] === $c);
            $summary[$c] = ['parts' => count($set), 'value' => round(array_sum(array_column($set, 'value')), 3), 'share' => $total > 0 ? round(array_sum(array_column($set, 'value')) * 100 / $total, 1) : 0];
        }

        return ['rows' => $rows, 'summary' => $summary, 'total' => round($total, 3)];
    }

    /** Value of the stock by age of its cost layers (days since received). */
    public function aging(?int $warehouseId = null): array
    {
        $buckets = ['0_90' => [0, 90], '91_180' => [91, 180], '181_365' => [181, 365], '365_plus' => [366, 100000]];
        $out = array_fill_keys(array_keys($buckets), ['qty' => 0.0, 'value' => 0.0, 'parts' => 0]);
        $byPart = [];
        DB::table('stock_batches')->where('company_id', tenant()->id())->where('qty_remaining', '>', 0)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->select('part_id', 'qty_remaining', 'unit_cost', 'received_at')->orderBy('id')
            ->each(function ($b) use (&$out, &$byPart, $buckets) {
                $age = (int) now()->diffInDays($b->received_at, true);
                foreach ($buckets as $k => [$lo, $hi]) {
                    if ($age >= $lo && $age <= $hi) {
                        $out[$k]['qty'] += (float) $b->qty_remaining;
                        $out[$k]['value'] += (float) $b->qty_remaining * (float) $b->unit_cost;
                        $byPart[$k][$b->part_id] = true;
                        if ($k === '365_plus' || $k === '181_365') {
                            $byPart['old'][$b->part_id] = ($byPart['old'][$b->part_id] ?? 0) + (float) $b->qty_remaining * (float) $b->unit_cost;
                        }
                        break;
                    }
                }
            });
        foreach ($out as $k => $v) {
            $out[$k]['parts'] = count($byPart[$k] ?? []);
            $out[$k]['value'] = round($v['value'], 3);
        }
        $old = $byPart['old'] ?? [];
        arsort($old);
        $oldest = collect(array_slice($old, 0, 15, true));

        return ['buckets' => $out, 'total' => round(array_sum(array_column($out, 'value')), 3),
            'oldest' => Part::query()->whereIn('id', $oldest->keys())->get()->map(fn ($p) => ['part' => $p, 'value' => round($oldest[$p->id], 3)])->sortByDesc('value')->values()];
    }

    /** Next-month forecast for the most consumed parts. */
    public function forecasts(int $limit = 30): array
    {
        $ids = DB::table('stock_movements')->where('company_id', tenant()->id())->where('moved_at', '>=', now()->subMonths(6))
            ->whereIn('type', ['sale', 'job_issue'])->groupBy('part_id')->orderByRaw('SUM(qty) ASC')->limit($limit)->pluck('part_id');
        $parts = Part::query()->whereIn('id', $ids)->withSum('stockLevels as on_hand', 'qty_on_hand')->get()->keyBy('id');
        $out = [];
        foreach ($ids as $id) {
            $p = $parts->get($id);
            if (! $p) {
                continue;
            }
            $series = $this->reorder->monthlyConsumption($p->id);
            $f = $this->reorder->forecast($series);
            $out[] = ['part' => $p, 'history' => $series, 'forecast' => $f, 'on_hand' => (float) ($p->on_hand ?? 0),
                'cover' => $f > 0 ? round((float) ($p->on_hand ?? 0) / $f, 1) : null];
        }

        return $out;
    }

    /**
     * Automatic reordering: draft purchase orders per default supplier from the reorder suggestions, skipping parts
     * that already have an open purchase order line. Returns the orders created.
     */
    public function autoReorder(int $warehouseId): array
    {
        $open = DB::table('purchase_order_items as i')->join('purchase_orders as o', 'o.id', '=', 'i.purchase_order_id')
            ->where('o.company_id', tenant()->id())->whereNotIn('o.status', ['received', 'closed', 'cancelled', 'rejected'])
            ->whereColumn('i.received_qty', '<', 'i.qty')->pluck('i.part_id')->unique()->all();
        $groups = [];
        foreach ($this->reorder->suggestions($warehouseId) as $s) {
            $p = $s['part'];
            if (! $p->default_supplier_id || in_array($p->id, $open, true)) {
                continue;
            }
            $groups[$p->default_supplier_id][] = ['part_id' => $p->id, 'qty' => $s['suggested_qty'], 'unit_cost' => $p->purchase_price];
        }
        $orders = [];
        foreach ($groups as $supplierId => $items) {
            $orders[] = app(PurchasingService::class)->createOrder(['supplier_id' => $supplierId, 'warehouse_id' => $warehouseId,
                'notes' => __('Automatic reorder (:d)', ['d' => now()->format('Y-m-d')])], $items);
        }
        if ($orders) {
            AuditLogger::log('inventory.auto_reorder', null, ['orders' => count($orders)]);
        }

        return $orders;
    }

    /** Parts due for counting in a warehouse (by ABC class frequency), oldest count first. */
    public function dueForCount(int $warehouseId, int $limit = 50)
    {
        return StockLevel::query()->with('part')->where('warehouse_id', $warehouseId)->where('qty_on_hand', '!=', 0)->get()
            ->filter(function ($l) {
                $days = self::CYCLE_DAYS[$l->part?->abc_class ?? ''] ?? 180;

                return ! $l->last_counted_at || $l->last_counted_at->lt(now()->subDays($days));
            })
            ->sortBy(fn ($l) => [['A' => 0, 'B' => 1, 'C' => 2][$l->part?->abc_class ?? ''] ?? 3, $l->last_counted_at?->timestamp ?? 0])
            ->take($limit)->values();
    }

    public function createCycleCount(int $warehouseId, int $limit = 50): ?StockCount
    {
        $due = $this->dueForCount($warehouseId, $limit);
        if ($due->isEmpty()) {
            return null;
        }

        return app(InventoryDocuments::class)->createCount($warehouseId, 'cycle', $due->pluck('part_id')->all(),
            __('Cycle count: :n parts due by ABC class', ['n' => $due->count()]));
    }

    /** Record a purchase price (goods receipt, RFQ quote or manual). */
    public static function recordPrice(int $partId, int $supplierId, mixed $unitCost, mixed $qty = 0, string $source = 'grn', ?string $reference = null, ?string $date = null): void
    {
        DB::table('supplier_price_history')->insert([
            'company_id' => tenant()->id(), 'part_id' => $partId, 'supplier_id' => $supplierId, 'unit_cost' => $unitCost, 'qty' => $qty ?: 0,
            'source' => $source, 'reference' => $reference, 'recorded_at' => $date ?? now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Price history of a part with the change from the previous price of the same supplier. */
    public function priceHistory(int $partId, int $limit = 40)
    {
        $rows = DB::table('supplier_price_history as h')->join('suppliers as s', 's.id', '=', 'h.supplier_id')
            ->where('h.company_id', tenant()->id())->where('h.part_id', $partId)
            ->orderBy('h.recorded_at')->orderBy('h.id')->get(['h.*', 's.name as supplier']);
        $last = [];
        foreach ($rows as $r) {
            $prev = $last[$r->supplier_id] ?? null;
            $r->change = $prev && (float) $prev > 0 ? round(((float) $r->unit_cost - (float) $prev) * 100 / (float) $prev, 1) : null;
            $last[$r->supplier_id] = $r->unit_cost;
        }

        return $rows->reverse()->take($limit)->values();
    }
}
