<?php

namespace App\Modules\Inventory\Services;

use App\Exceptions\BusinessException;
use App\Modules\Inventory\Models\Part;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Inventory kitting: a kit part (e.g. "10,000 km service kit") is built from component parts. Assembling issues
 * the components and receives the kits at their total cost; disassembling does the reverse. No accounting entry is
 * needed: the value only moves from the components to the kit.
 */
class KitService
{
    public function __construct(protected StockService $stock) {}

    /** @param array<int, array{component_part_id: int, qty: mixed}> $rows */
    public function setComponents(Part $kit, array $rows): void
    {
        DB::transaction(function () use ($kit, $rows) {
            $kit = Part::query()->lockForUpdate()->findOrFail($kit->id);
            if (collect($rows)->contains(fn ($row) => ! empty($row['component_part_id']) && Money::isPositive($row['qty'] ?? 0))
                && \App\Modules\Inventory\Models\PartKitItem::query()->where('component_part_id', $kit->id)->exists()) {
                throw new BusinessException(__('A kit cannot contain another kit.'));
            }
            $kit->kitItems()->delete();
            foreach ($rows as $r) {
                $id = (int) ($r['component_part_id'] ?? 0);
                if (! $id || ! Money::isPositive($r['qty'] ?? 0)) {
                    continue;
                }
                if ($id === $kit->id) {
                    throw new BusinessException(__('A kit cannot contain itself.'));
                }
                if (Part::query()->whereKey($id)->value('is_kit')) {
                    throw new BusinessException(__('A kit cannot contain another kit.'));
                }
                $kit->kitItems()->updateOrCreate(['component_part_id' => $id], ['qty' => Money::round($r['qty'])]);
            }
            $kit->forceFill(['is_kit' => $kit->kitItems()->exists()])->save();
        });
    }

    /** How many kits the components in stock allow in a warehouse. */
    public function buildable(Part $kit, int $warehouseId): int
    {
        $max = null;
        foreach ($kit->kitItems()->with('component')->get() as $i) {
            $can = (int) floor((float) $i->component->available($warehouseId) / max(0.001, (float) $i->qty));
            $max = $max === null ? $can : min($max, $can);
        }

        return max(0, (int) $max);
    }

    public function assemble(Part $kit, int $warehouseId, mixed $qty): string
    {
        $items = $kit->kitItems()->with('component')->get();
        if ($items->isEmpty()) {
            throw new BusinessException(__('Add the kit components first.'));
        }

        return DB::transaction(function () use ($kit, $warehouseId, $qty, $items) {
            $cost = '0';
            foreach ($items as $i) {
                $r = $this->stock->issue($i->component, $warehouseId, Money::mul($i->qty, $qty), ['type' => 'kit_assembly', 'reference' => $kit, 'notes' => __('Kit assembly')]);
                $cost = Money::add($cost, $r['total_cost']);
            }
            $this->stock->receive($kit, $warehouseId, $qty, Money::div($cost, $qty, 4), ['type' => 'kit_assembly', 'reference' => $kit, 'notes' => __('Kit assembly')]);
            AuditLogger::log('stock.kit_assembled', $kit, ['qty' => (string) $qty, 'cost' => $cost]);

            return $cost;
        });
    }

    public function disassemble(Part $kit, int $warehouseId, mixed $qty): void
    {
        $items = $kit->kitItems()->with('component')->get();
        if ($items->isEmpty()) {
            throw new BusinessException(__('Add the kit components first.'));
        }
        DB::transaction(function () use ($kit, $warehouseId, $qty, $items) {
            $r = $this->stock->issue($kit, $warehouseId, $qty, ['type' => 'kit_disassembly', 'reference' => $kit, 'notes' => __('Kit disassembly')]);
            // split the kit cost over the components by their current average cost
            $weights = $items->mapWithKeys(fn ($i) => [$i->id => (float) $i->qty * max(0.0001, (float) ($i->component->average_cost ?: $i->component->purchase_price))]);
            $sum = max(0.0001, $weights->sum());
            foreach ($items as $i) {
                $lineCost = (float) $r['total_cost'] * $weights[$i->id] / $sum;
                $q = Money::mul($i->qty, $qty);
                $this->stock->receive($i->component, $warehouseId, $q, Money::div((string) round($lineCost, 4), $q, 4), ['type' => 'kit_disassembly', 'reference' => $kit, 'notes' => __('Kit disassembly')]);
            }
        });
    }
}
