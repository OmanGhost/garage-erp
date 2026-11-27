<?php

namespace App\Modules\Inventory\Services;

use App\Exceptions\BusinessException;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single entry point for every stock quantity change.
 *
 * - Row-level locks (SELECT ... FOR UPDATE) on stock_levels / stock_batches prevent
 *   concurrent issues from overselling.
 * - Valuation: weighted average (per warehouse) or FIFO cost layers (stock_batches).
 * - Negative stock is rejected unless allowed by settings or explicitly by the caller.
 * - Every change writes an immutable stock_movements row.
 */
class StockService
{
    public const IN_TYPES = ['purchase_receipt', 'job_return', 'transfer_in', 'adjustment_in', 'dismantle_in', 'sales_return', 'opening', 'count_gain', 'consignment_in', 'kit_assembly', 'kit_disassembly'];
    public const OUT_TYPES = ['sale', 'job_issue', 'transfer_out', 'adjustment_out', 'damage', 'purchase_return', 'count_loss', 'scrap', 'consignment_return', 'kit_assembly', 'kit_disassembly'];

    public function method(): string
    {
        return Settings::get('inventory.valuation_method', 'weighted_average') === 'fifo' ? 'fifo' : 'weighted_average';
    }

    /** Locked stock level row (created on demand). */
    public function lockLevel(int $partId, int $warehouseId): StockLevel
    {
        $level = StockLevel::query()->where('part_id', $partId)->where('warehouse_id', $warehouseId)->lockForUpdate()->first();
        if (! $level) {
            try {
                StockLevel::create(['part_id' => $partId, 'warehouse_id' => $warehouseId, 'qty_on_hand' => 0, 'qty_reserved' => 0, 'avg_cost' => 0]);
            } catch (\Illuminate\Database\QueryException) {
                // created concurrently
            }
            $level = StockLevel::query()->where('part_id', $partId)->where('warehouse_id', $warehouseId)->lockForUpdate()->firstOrFail();
        }

        return $level;
    }

    /**
     * Add stock.
     *
     * @param  array{type?:string,reference?:Model,reference_number?:string,batch_number?:string,serials?:array,expiry_date?:string,
     *               location_id?:int,dismantled_vehicle_id?:int,grade?:string,notes?:string,date?:string,update_purchase_price?:bool}  $opt
     * @return array{movement:StockMovement,batches:array<int>,total_cost:string}
     */
    public function receive(Part|int $part, int $warehouseId, mixed $qty, mixed $unitCost, array $opt = []): array
    {
        $part = $part instanceof Part ? $part : Part::query()->findOrFail($part);
        $qty = Money::round($qty);
        $unitCost = Money::round($unitCost, 4);
        if (! Money::isPositive($qty)) {
            throw new BusinessException(__('Quantity must be greater than zero.'));
        }
        if (Money::cmp($unitCost, 0) < 0) {
            throw new BusinessException(__('Unit cost cannot be negative.'));
        }

        return DB::transaction(function () use ($part, $warehouseId, $qty, $unitCost, $opt) {
            $level = $this->lockLevel($part->id, $warehouseId);
            $oldQty = (string) $level->qty_on_hand;
            $newQty = Money::add($oldQty, $qty);

            if (Money::cmp($oldQty, 0) <= 0 || Money::cmp($newQty, 0) <= 0) {
                $avg = $unitCost;
            } else {
                $value = bcadd(bcmul($oldQty, (string) $level->avg_cost, 8), bcmul($qty, $unitCost, 8), 8);
                $avg = Money::round(bcdiv($value, $newQty, 8), 4);
            }
            $level->qty_on_hand = $newQty;
            $level->avg_cost = $avg;
            $level->last_movement_at = now();
            if (! empty($opt['location_id'])) {
                $level->location_id = $opt['location_id'];
            }
            $level->save();

            // cost layers / serials / batches
            $batchIds = [];
            $serials = array_values(array_filter(array_map('trim', (array) ($opt['serials'] ?? []))));
            $base = [
                'part_id' => $part->id, 'warehouse_id' => $warehouseId, 'batch_number' => $opt['batch_number'] ?? null,
                'expiry_date' => $opt['expiry_date'] ?? null, 'unit_cost' => $unitCost, 'received_at' => $opt['date'] ?? now(),
                'source_type' => isset($opt['reference']) ? $opt['reference']->getMorphClass() : null,
                'source_id' => isset($opt['reference']) ? $opt['reference']->getKey() : null,
                'dismantled_vehicle_id' => $opt['dismantled_vehicle_id'] ?? null, 'grade' => $opt['grade'] ?? null,
            ];
            if ($serials) {
                if (Money::cmp(count($serials), $qty) !== 0) {
                    throw new BusinessException(__('Number of serial numbers must equal the received quantity for :p.', ['p' => $part->sku]));
                }
                foreach ($serials as $sn) {
                    $batchIds[] = StockBatch::create($base + ['serial_number' => $sn, 'qty_received' => 1, 'qty_remaining' => 1])->id;
                }
            } else {
                $batchIds[] = StockBatch::create($base + ['serial_number' => $opt['serial_number'] ?? null, 'qty_received' => $qty, 'qty_remaining' => $qty])->id;
            }

            $total = Money::mul($qty, $unitCost);
            $movement = $this->movement($part->id, $warehouseId, $opt['type'] ?? 'purchase_receipt', $qty, $unitCost, $total, $newQty, $opt, $batchIds[0]);

            // company-wide weighted average on the part card
            $agg = StockLevel::query()->where('part_id', $part->id)->selectRaw('SUM(qty_on_hand) q, SUM(qty_on_hand * avg_cost) v')->first();
            $part->average_cost = Money::cmp($agg->q, 0) > 0 ? Money::round(bcdiv(Money::n($agg->v), Money::n($agg->q), 8), 4) : $avg;
            if (($opt['update_purchase_price'] ?? ($opt['type'] ?? 'purchase_receipt') === 'purchase_receipt')) {
                $part->purchase_price = $unitCost;
            }
            $part->saveQuietly();

            return ['movement' => $movement, 'batches' => $batchIds, 'total_cost' => $total];
        });
    }

    /**
     * Remove stock and compute its cost.
     *
     * @param  array{type?:string,reference?:Model,reference_number?:string,from_reserved?:mixed,allow_negative?:bool,
     *               serial_number?:string,batch_id?:int,notes?:string,date?:string}  $opt
     * @return array{total_cost:string,unit_cost:string,movement:StockMovement,batches:array}
     */
    public function issue(Part|int $part, int $warehouseId, mixed $qty, array $opt = []): array
    {
        $part = $part instanceof Part ? $part : Part::query()->findOrFail($part);
        $qty = Money::round($qty);
        if (! Money::isPositive($qty)) {
            throw new BusinessException(__('Quantity must be greater than zero.'));
        }

        return DB::transaction(function () use ($part, $warehouseId, $qty, $opt) {
            $level = $this->lockLevel($part->id, $warehouseId);
            $fromReserved = Money::min(Money::round($opt['from_reserved'] ?? 0), $level->qty_reserved);
            // stock that is free for this request = on hand - reservations of others
            $free = Money::sub($level->qty_on_hand, Money::sub($level->qty_reserved, $fromReserved));
            $allowNegative = ($opt['allow_negative'] ?? false) || Settings::bool('inventory.allow_negative');
            if (Money::cmp($qty, $free) > 0 && ! $allowNegative) {
                throw new BusinessException(__('Insufficient stock for :part. Available: :qty', ['part' => $part->label(), 'qty' => qty(Money::max($free, 0))]));
            }

            // consume cost layers (always FIFO for quantity tracking)
            $q = StockBatch::query()->where('part_id', $part->id)->where('warehouse_id', $warehouseId)->where('qty_remaining', '>', 0);
            if (! empty($opt['serial_number'])) {
                $q->where('serial_number', $opt['serial_number']);
            }
            if (! empty($opt['batch_id'])) {
                $q->whereKey($opt['batch_id']);
            }
            $batches = $q->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')->orderBy('expiry_date')
                ->orderBy('received_at')->orderBy('id')->lockForUpdate()->get();
            if ((! empty($opt['serial_number']) || ! empty($opt['batch_id'])) && $batches->isEmpty()) {
                throw new BusinessException(__('The selected serial/batch is not available in this warehouse.'));
            }

            $remaining = $qty;
            $fifoCost = '0';
            $used = [];
            foreach ($batches as $b) {
                if (! Money::isPositive($remaining)) {
                    break;
                }
                $take = Money::min($remaining, $b->qty_remaining);
                $b->qty_remaining = Money::sub($b->qty_remaining, $take);
                $b->save();
                $fifoCost = bcadd($fifoCost, bcmul($take, (string) $b->unit_cost, 8), 8);
                $remaining = Money::sub($remaining, $take);
                $used[] = ['batch_id' => $b->id, 'qty' => $take, 'unit_cost' => (string) $b->unit_cost, 'serial' => $b->serial_number, 'dismantled_vehicle_id' => $b->dismantled_vehicle_id];
            }
            $fallbackCost = Money::cmp($level->avg_cost, 0) > 0 ? (string) $level->avg_cost : (string) $part->average_cost;
            if (Money::isPositive($remaining)) {
                $fifoCost = bcadd($fifoCost, bcmul($remaining, $fallbackCost, 8), 8);
            }

            $total = $this->method() === 'fifo'
                ? Money::round($fifoCost)
                : Money::mul($qty, Money::cmp($level->avg_cost, 0) > 0 ? $level->avg_cost : $fallbackCost);
            $unit = Money::div($total, $qty, 4);

            $level->qty_on_hand = Money::sub($level->qty_on_hand, $qty);
            $level->qty_reserved = Money::max(0, Money::sub($level->qty_reserved, $fromReserved));
            if ($this->method() === 'fifo') {
                $val = StockBatch::query()->where('part_id', $part->id)->where('warehouse_id', $warehouseId)->where('qty_remaining', '>', 0)
                    ->selectRaw('SUM(qty_remaining) q, SUM(qty_remaining * unit_cost) v')->first();
                if (Money::cmp($val->q, 0) > 0) {
                    $level->avg_cost = Money::round(bcdiv(Money::n($val->v), Money::n($val->q), 8), 4);
                }
            }
            $level->last_movement_at = now();
            $level->save();

            $movement = $this->movement($part->id, $warehouseId, $opt['type'] ?? 'sale', Money::neg($qty), $unit, Money::neg($total),
                $level->qty_on_hand, $opt, $used[0]['batch_id'] ?? null);

            return ['total_cost' => $total, 'unit_cost' => $unit, 'movement' => $movement, 'batches' => $used];
        });
    }

    /** Reserve stock for a job card / order (no physical movement). */
    public function reserve(int $partId, int $warehouseId, mixed $qty, bool $allowShortage = false): string
    {
        return DB::transaction(function () use ($partId, $warehouseId, $qty, $allowShortage) {
            $level = $this->lockLevel($partId, $warehouseId);
            $available = Money::max(0, $level->available());
            $qty = Money::round($qty);
            if (Money::cmp($qty, $available) > 0) {
                if (! $allowShortage) {
                    $part = Part::query()->find($partId);
                    throw new BusinessException(__('Insufficient stock for :part. Available: :qty', ['part' => $part?->label(), 'qty' => qty($available)]));
                }
                $qty = $available;
            }
            $level->qty_reserved = Money::add($level->qty_reserved, $qty);
            $level->save();

            return $qty;
        });
    }

    public function release(int $partId, int $warehouseId, mixed $qty): void
    {
        DB::transaction(function () use ($partId, $warehouseId, $qty) {
            $level = $this->lockLevel($partId, $warehouseId);
            $level->qty_reserved = Money::max(0, Money::sub($level->qty_reserved, $qty));
            $level->save();
        });
    }

    /**
     * Move stock between warehouses at cost (no GL impact within one company).
     *
     * @return string total cost moved
     */
    public function transfer(int $partId, int $fromWarehouseId, int $toWarehouseId, mixed $qty, array $opt = []): string
    {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new BusinessException(__('Source and destination warehouses must differ.'));
        }

        return DB::transaction(function () use ($partId, $fromWarehouseId, $toWarehouseId, $qty, $opt) {
            $out = $this->issue($partId, $fromWarehouseId, $qty, $opt + ['type' => 'transfer_out']);
            // keep serial / batch identity and individual layer cost
            foreach ($out['batches'] as $b) {
                $this->receive($partId, $toWarehouseId, $b['qty'], $b['unit_cost'], $opt + [
                    'type' => 'transfer_in', 'serial_number' => $b['serial'], 'dismantled_vehicle_id' => $b['dismantled_vehicle_id'],
                    'update_purchase_price' => false,
                ]);
            }
            $covered = Money::sum(array_column($out['batches'], 'qty'));
            $rest = Money::sub($qty, $covered);
            if (Money::isPositive($rest)) {
                $this->receive($partId, $toWarehouseId, $rest, $out['unit_cost'], $opt + ['type' => 'transfer_in', 'update_purchase_price' => false]);
            }

            return $out['total_cost'];
        });
    }

    /** Signed adjustment. Returns ['qty' => signed qty, 'value' => signed value]. */
    public function adjust(int $partId, int $warehouseId, mixed $signedQty, ?string $unitCost = null, array $opt = []): array
    {
        $signedQty = Money::round($signedQty);
        if (Money::isZero($signedQty)) {
            return ['qty' => '0', 'value' => '0'];
        }
        if (Money::isPositive($signedQty)) {
            $level = StockLevel::query()->where('part_id', $partId)->where('warehouse_id', $warehouseId)->first();
            $cost = $unitCost ?? ($level && Money::isPositive($level->avg_cost) ? (string) $level->avg_cost : (string) Part::query()->find($partId)?->average_cost);
            $r = $this->receive($partId, $warehouseId, $signedQty, $cost, $opt + ['type' => $opt['type'] ?? 'adjustment_in', 'update_purchase_price' => false]);

            return ['qty' => $signedQty, 'value' => $r['total_cost']];
        }
        $r = $this->issue($partId, $warehouseId, Money::neg($signedQty), $opt + ['type' => $opt['type'] ?? 'adjustment_out', 'allow_negative' => $opt['allow_negative'] ?? false]);

        return ['qty' => $signedQty, 'value' => Money::neg($r['total_cost'])];
    }

    protected function movement(int $partId, int $warehouseId, string $type, string $qty, string $unitCost, string $total, mixed $balance, array $opt, ?int $batchId): StockMovement
    {
        $ref = $opt['reference'] ?? null;

        return StockMovement::create([
            'branch_id' => tenant()->branchId(),
            'warehouse_id' => $warehouseId,
            'part_id' => $partId,
            'batch_id' => $batchId,
            'type' => $type,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'total_cost' => $total,
            'balance_after' => $balance,
            'reference_type' => $ref?->getMorphClass(),
            'reference_id' => $ref?->getKey(),
            'reference_number' => $opt['reference_number'] ?? ($ref->number ?? null),
            'user_id' => auth()->id(),
            'notes' => isset($opt['notes']) ? mb_substr($opt['notes'], 0, 250) : null,
            'moved_at' => $opt['date'] ?? now(),
        ]);
    }

    /** Inventory valuation (qty * avg cost) per warehouse or total. */
    public function valuation(?int $warehouseId = null): string
    {
        $q = StockLevel::query()->where('qty_on_hand', '>', 0);
        if ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        }

        return Money::round($q->sum(DB::raw('qty_on_hand * avg_cost')));
    }
}
