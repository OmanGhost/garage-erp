<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Part;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Low-stock alerts, reorder suggestions and demand forecasting
 * (Holt-style exponential smoothing on monthly consumption).
 */
class ReorderService
{
    /** Monthly consumption (sales + job issues - returns) for the last $months months. */
    public function monthlyConsumption(int $partId, int $months = 6): array
    {
        $from = now()->startOfMonth()->subMonths($months - 1);
        // aggregated in PHP to stay database-portable (MySQL / SQLite)
        $rows = [];
        DB::table('stock_movements')->where('company_id', tenant()->id())->where('part_id', $partId)
            ->whereIn('type', ['sale', 'job_issue', 'job_return', 'sales_return'])
            ->where('moved_at', '>=', $from)->select('moved_at', 'qty')->orderBy('id')
            ->each(function ($m) use (&$rows) {
                $ym = substr((string) $m->moved_at, 0, 7);
                $rows[$ym] = ($rows[$ym] ?? 0) - (float) $m->qty;
            });
        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $ym = $from->copy()->addMonths($i)->format('Y-m');
            $out[$ym] = max(0.0, (float) ($rows[$ym] ?? 0));
        }

        return $out;
    }

    /** Forecast next month demand with double exponential smoothing. */
    public function forecast(array $series, float $alpha = 0.5, float $beta = 0.3): float
    {
        $values = array_values($series);
        if (! $values) {
            return 0.0;
        }
        if (count($values) === 1) {
            return $values[0];
        }
        $level = $values[0];
        $trend = $values[1] - $values[0];
        foreach (array_slice($values, 1) as $v) {
            $prev = $level;
            $level = $alpha * $v + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $prev) + (1 - $beta) * $trend;
        }

        return max(0.0, round($level + $trend, 3));
    }

    /** Parts at/below minimum stock with suggested order quantities. */
    public function suggestions(?int $warehouseId = null, int $limit = 200): array
    {
        $parts = Part::query()->where('is_active', true)->where('part_type', '!=', 'scrap')
            ->withSum(['stockLevels as on_hand' => fn ($q) => $warehouseId ? $q->where('warehouse_id', $warehouseId) : $q], 'qty_on_hand')
            ->withSum(['stockLevels as reserved' => fn ($q) => $warehouseId ? $q->where('warehouse_id', $warehouseId) : $q], 'qty_reserved')
            ->with('supplier')->get();
        $out = [];
        foreach ($parts as $p) {
            $available = Money::sub($p->on_hand ?? 0, $p->reserved ?? 0);
            $series = $this->monthlyConsumption($p->id);
            $forecast = $this->forecast($series);
            $belowMin = Money::cmp($available, $p->min_stock) <= 0 && (Money::isPositive($p->min_stock) || $forecast > 0);
            $coverLow = $forecast > 0 && (float) $available < $forecast;
            if (! $belowMin && ! $coverLow) {
                continue;
            }
            $target = max((float) $p->min_stock + $forecast * 1.5, (float) $p->min_stock);
            $suggest = max((float) $p->reorder_qty, ceil($target - (float) $available));
            if ($p->max_stock !== null && (float) $p->max_stock > 0) {
                $suggest = min($suggest, max(0, (float) $p->max_stock - (float) $available));
            }
            if ($suggest <= 0) {
                continue;
            }
            $out[] = [
                'part' => $p, 'available' => $available, 'min_stock' => $p->min_stock, 'forecast' => $forecast,
                'suggested_qty' => $suggest, 'estimated_cost' => Money::mul($suggest, $p->purchase_price), 'history' => $series,
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** Parts without movement for $days days that still have stock. */
    public function slowMoving(int $days = 180)
    {
        $cut = now()->subDays($days);

        return DB::table('stock_levels as s')->join('parts as p', 'p.id', '=', 's.part_id')
            ->join('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->where('s.company_id', tenant()->id())->where('s.qty_on_hand', '>', 0)
            ->where(fn ($q) => $q->whereNull('s.last_movement_at')->orWhere('s.last_movement_at', '<', $cut))
            ->whereNotExists(fn ($q) => $q->from('stock_movements as m')->whereColumn('m.part_id', 's.part_id')
                ->whereColumn('m.warehouse_id', 's.warehouse_id')->where('m.qty', '<', 0)->where('m.moved_at', '>=', $cut))
            ->select('p.id', 'p.sku', 'p.name_ar', 'p.name_en', 'w.name as warehouse', 's.qty_on_hand', 's.avg_cost',
                DB::raw('s.qty_on_hand * s.avg_cost as value'), 's.last_movement_at')
            ->orderByDesc('value');
    }
}
