<?php

namespace App\Modules\Inventory\Services;

use App\Exceptions\BusinessException;
use App\Modules\Inventory\Models\StockReservation;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/** Manual stock reservations (for a customer, job card or sales order) with expiry. */
class ReservationService
{
    public function __construct(protected StockService $stock) {}

    public function reserve(array $data): StockReservation
    {
        return DB::transaction(function () use ($data) {
            $qty = $this->stock->reserve((int) $data['part_id'], (int) $data['warehouse_id'], $data['qty']);   // throws when not available
            $r = StockReservation::create([
                'number' => DocumentNumber::next('stock_reservation'), 'part_id' => $data['part_id'], 'warehouse_id' => $data['warehouse_id'], 'qty' => $qty,
                'customer_id' => $data['customer_id'] ?? null, 'job_card_id' => $data['job_card_id'] ?? null, 'sales_order_id' => $data['sales_order_id'] ?? null,
                'reason' => $data['reason'] ?? null, 'expires_at' => $data['expires_at'] ?? now()->addDays(7)->toDateString(),
                'status' => 'active', 'reserved_by' => auth()->id(),
            ]);
            AuditLogger::log('stock.reserved', $r, ['part' => $r->part_id, 'qty' => (string) $qty]);

            return $r;
        });
    }

    /** Free the stock again ($status: released by hand, consumed when sold, expired by the nightly job). */
    public function release(StockReservation $r, string $status = 'released'): void
    {
        DB::transaction(function () use ($r, $status) {
            $r = StockReservation::query()->lockForUpdate()->findOrFail($r->id);
            if ($r->status !== 'active') {
                return;
            }
            $this->stock->release($r->part_id, $r->warehouse_id, $r->qty);
            $r->update(['status' => $status, 'released_at' => now()]);
        });
    }

    public function expire(): int
    {
        $n = 0;
        foreach (StockReservation::query()->where('status', 'active')->whereDate('expires_at', '<', today())->get() as $r) {
            $this->release($r, 'expired');
            $n++;
        }

        return $n;
    }
}
