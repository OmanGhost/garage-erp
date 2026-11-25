<?php

namespace App\Modules\Inventory\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockTransfer;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Transfers, stock counts (stocktaking) and adjustments with GL impact.
 */
class InventoryDocuments
{
    public function __construct(protected StockService $stock, protected PostingService $posting) {}

    // ------------------------------------------------------------ transfers

    public function createTransfer(array $data, array $items): StockTransfer
    {
        return DB::transaction(function () use ($data, $items) {
            $t = StockTransfer::create($data + ['number' => DocumentNumber::next('stock_transfer'), 'status' => 'draft', 'created_by' => auth()->id()]);
            foreach ($items as $i) {
                if (Money::isPositive($i['qty'] ?? 0)) {
                    $t->items()->create(['part_id' => $i['part_id'], 'qty' => $i['qty']]);
                }
            }
            if ($t->items()->count() === 0) {
                throw new BusinessException(__('Add at least one item.'));
            }

            return $t;
        });
    }

    /** Dispatch: stock leaves the source warehouse (in transit). */
    public function sendTransfer(StockTransfer $t): void
    {
        DB::transaction(function () use ($t) {
            $t = StockTransfer::query()->whereKey($t->id)->lockForUpdate()->firstOrFail();
            if ($t->status !== 'draft') {
                throw new BusinessException(__('Only draft transfers can be sent.'));
            }
            foreach ($t->items as $item) {
                $r = $this->stock->issue($item->part_id, $t->from_warehouse_id, $item->qty, ['type' => 'transfer_out', 'reference' => $t]);
                $item->update(['unit_cost' => $r['unit_cost']]);
            }
            $t->update(['status' => 'in_transit', 'sent_at' => now()]);
        });
    }

    /** Receive at destination (full quantities unless $received given per item id). */
    public function receiveTransfer(StockTransfer $t, array $received = []): void
    {
        DB::transaction(function () use ($t, $received) {
            $t = StockTransfer::query()->whereKey($t->id)->lockForUpdate()->firstOrFail();
            if ($t->status === 'draft') {
                $this->sendTransfer($t);
                $t->refresh();
            }
            if ($t->status !== 'in_transit') {
                throw new BusinessException(__('Transfer is not in transit.'));
            }
            foreach ($t->items as $item) {
                $qty = Money::min($received[$item->id] ?? $item->qty, $item->qty);
                if (Money::isPositive($qty)) {
                    $this->stock->receive($item->part_id, $t->to_warehouse_id, $qty, $item->unit_cost, ['type' => 'transfer_in', 'reference' => $t, 'update_purchase_price' => false]);
                }
                $short = Money::sub($item->qty, $qty);
                if (Money::isPositive($short)) {
                    // shortage in transit is written off as loss
                    $this->posting->post([
                        'date' => now()->toDateString(), 'description' => __('Transfer shortage').' '.$t->number,
                        'source' => $t, 'purpose' => 'shortage:'.$item->id,
                        'lines' => [
                            ['account' => 'inventory_loss', 'debit' => Money::mul($short, $item->unit_cost)],
                            ['account' => 'inventory', 'credit' => Money::mul($short, $item->unit_cost)],
                        ],
                    ]);
                }
                $item->update(['received_qty' => $qty]);
            }
            $t->update(['status' => 'received', 'received_at' => now(), 'received_by' => auth()->id()]);
        });
    }

    // --------------------------------------------------------------- counts

    public function createCount(int $warehouseId, string $type = 'periodic', ?array $partIds = null, ?string $notes = null): StockCount
    {
        return DB::transaction(function () use ($warehouseId, $type, $partIds, $notes) {
            $count = StockCount::create([
                'number' => DocumentNumber::next('stock_count'), 'warehouse_id' => $warehouseId, 'type' => $type,
                'status' => 'in_progress', 'count_date' => now()->toDateString(), 'notes' => $notes, 'created_by' => auth()->id(),
            ]);
            $levels = StockLevel::query()->where('warehouse_id', $warehouseId)
                ->when($partIds, fn ($q) => $q->whereIn('part_id', $partIds))->get();
            foreach ($levels as $l) {
                $count->items()->create(['part_id' => $l->part_id, 'system_qty' => $l->qty_on_hand, 'unit_cost' => $l->avg_cost]);
            }

            return $count;
        });
    }

    public function postCount(StockCount $count): void
    {
        DB::transaction(function () use ($count) {
            $count = StockCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();
            if ($count->status === 'posted') {
                return;
            }
            $gain = $loss = '0';
            foreach ($count->items as $item) {
                if ($item->counted_qty === null) {
                    continue;
                }
                $diff = Money::sub($item->counted_qty, $item->system_qty);
                $item->update(['difference' => $diff]);
                if (Money::isZero($diff)) {
                    continue;
                }
                $r = $this->stock->adjust($item->part_id, $count->warehouse_id, $diff, (string) $item->unit_cost, [
                    'type' => Money::isPositive($diff) ? 'count_gain' : 'count_loss', 'reference' => $count, 'allow_negative' => true,
                ]);
                Money::isPositive($r['value']) ? $gain = Money::add($gain, $r['value']) : $loss = Money::add($loss, Money::neg($r['value']));
            }
            $count->forceFill(['status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id()])->save();
            $je = $this->postGainLoss($count, $gain, $loss, __('Stock count').' '.$count->number);
            if ($je) {
                $count->forceFill(['journal_entry_id' => $je->id])->save();
            }
            AuditLogger::log('stock_count.posted', $count, ['gain' => $gain, 'loss' => $loss]);
        });
    }

    // ---------------------------------------------------------- adjustments

    public function postAdjustment(StockAdjustment $adj): void
    {
        DB::transaction(function () use ($adj) {
            $adj = StockAdjustment::query()->whereKey($adj->id)->lockForUpdate()->firstOrFail();
            if ($adj->status === 'posted') {
                return;
            }
            if ($adj->items()->count() === 0) {
                throw new BusinessException(__('Add at least one item.'));
            }
            $gain = $loss = '0';
            $type = match ($adj->reason) {
                'damage', 'expired' => 'damage',
                'opening' => 'opening',
                default => null,
            };
            foreach ($adj->items as $item) {
                $r = $this->stock->adjust($item->part_id, $adj->warehouse_id, $item->qty, Money::isPositive($item->unit_cost) ? (string) $item->unit_cost : null, [
                    'type' => $type ?? (Money::isPositive($item->qty) ? 'adjustment_in' : 'adjustment_out'), 'reference' => $adj, 'notes' => $item->notes,
                ]);
                $item->update(['unit_cost' => Money::div(Money::isPositive($r['value']) ? $r['value'] : Money::neg($r['value']), Money::isPositive($item->qty) ? $item->qty : Money::neg($item->qty), 4)]);
                Money::isPositive($r['value']) ? $gain = Money::add($gain, $r['value']) : $loss = Money::add($loss, Money::neg($r['value']));
            }
            $adj->forceFill(['status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id()])->save();
            $je = $this->postGainLoss($adj, $gain, $loss, __('Stock adjustment').' '.$adj->number, $adj->reason === 'opening' ? 'opening_equity' : 'inventory_gain');
            if ($je) {
                $adj->forceFill(['journal_entry_id' => $je->id])->save();
            }
            AuditLogger::log('stock_adjustment.posted', $adj, ['gain' => $gain, 'loss' => $loss]);
        });
    }

    protected function postGainLoss($doc, string $gain, string $loss, string $desc, string $gainAccount = 'inventory_gain')
    {
        $lines = [];
        if (Money::isPositive($gain)) {
            $lines[] = ['account' => 'inventory', 'debit' => $gain];
            $lines[] = ['account' => $gainAccount, 'credit' => $gain];
        }
        if (Money::isPositive($loss)) {
            $lines[] = ['account' => 'inventory_loss', 'debit' => $loss];
            $lines[] = ['account' => 'inventory', 'credit' => $loss];
        }

        return $lines ? $this->posting->post(['date' => now()->toDateString(), 'description' => $desc, 'source' => $doc, 'lines' => $lines]) : null;
    }
}
