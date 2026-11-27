<?php

namespace App\Modules\Inventory\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Models\ConsignmentSettlement;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Consignment stock: parts the supplier (consignor) leaves in a consignment warehouse and that are paid only once
 * sold. Receipts are booked Dr Inventory / Cr Goods-received-not-invoiced (owed to the consignor, not yet payable),
 * so sales and job issues work as usual. A settlement bills what was sold since the previous settlement as a
 * supplier invoice (Dr GRNI / Cr Payables); unsold parts can be returned (Dr GRNI / Cr Inventory).
 */
class ConsignmentService
{
    public const OUT_TYPES = ['sale', 'job_issue', 'sales_return', 'job_return'];

    public function __construct(protected StockService $stock, protected PostingService $posting) {}

    protected function assertConsignment(Warehouse $wh): void
    {
        if (! $wh->is_consignment || ! $wh->consignor_supplier_id) {
            throw new BusinessException(__('This warehouse is not a consignment warehouse with a consignor.'));
        }
    }

    /** @param array<int, array{part_id: int, qty: mixed, unit_cost: mixed}> $lines */
    public function receive(Warehouse $wh, array $lines, ?string $reference = null): string
    {
        $this->assertConsignment($wh);

        return DB::transaction(function () use ($wh, $lines, $reference) {
            $total = '0';
            foreach ($lines as $l) {
                if (! Money::isPositive($l['qty'] ?? 0)) {
                    continue;
                }
                $r = $this->stock->receive((int) $l['part_id'], $wh->id, $l['qty'], $l['unit_cost'], ['type' => 'consignment_in', 'reference' => $wh,
                    'reference_number' => $reference, 'notes' => __('Consignment from :s', ['s' => $wh->consignor?->name]), 'update_purchase_price' => false]);
                $total = Money::add($total, $r['total_cost']);
            }
            if (! Money::isPositive($total)) {
                throw new BusinessException(__('Add at least one part with quantity.'));
            }
            $this->posting->post(['date' => now()->toDateString(), 'description' => __('Consignment receipt').' '.$wh->name.($reference ? ' '.$reference : ''),
                'source' => $wh, 'purpose' => 'consignment_in:'.$r['movement']->id,
                'lines' => [['account' => 'inventory', 'debit' => $total], ['account' => 'grni', 'credit' => $total, 'party' => $wh->consignor]]]);
            AuditLogger::log('consignment.received', $wh, ['value' => $total]);

            return $total;
        });
    }

    public function lastSettlementAt(Warehouse $wh): ?\Illuminate\Support\Carbon
    {
        $t = ConsignmentSettlement::query()->where('warehouse_id', $wh->id)->max('period_to');

        return $t ? \Illuminate\Support\Carbon::parse($t) : null;
    }

    /** Net quantity and cost sold out of the warehouse since the last settlement, per part. */
    public function soldSince(Warehouse $wh): array
    {
        $movements = DB::table('stock_movements')->where('company_id', tenant()->id())->where('warehouse_id', $wh->id)
            ->whereIn('type', self::OUT_TYPES)->whereNull('consignment_settlement_id')->get(['id', 'part_id', 'qty', 'total_cost']);
        $parts = Part::query()->whereIn('id', $movements->pluck('part_id')->unique())->get()->keyBy('id');
        $sold = [];
        foreach ($movements->groupBy('part_id') as $id => $group) {
            $qty = Money::neg(Money::sum($group->pluck('qty')));
            if (Money::isPositive($qty) && $parts->has($id)) {
                $sold[] = ['part' => $parts[$id], 'qty' => $qty, 'cost' => Money::neg(Money::sum($group->pluck('total_cost'))),
                    'movement_ids' => $group->pluck('id')->all()];
            }
        }

        return $sold;
    }

    /** Bill the consignor for what was sold: a draft supplier invoice against GRNI. */
    public function settle(Warehouse $wh, ?string $supplierInvoiceNo = null): ConsignmentSettlement
    {
        $this->assertConsignment($wh);

        return DB::transaction(function () use ($wh, $supplierInvoiceNo) {
            $wh = Warehouse::query()->lockForUpdate()->findOrFail($wh->id);
            $sold = $this->soldSince($wh);
            if (! $sold) {
                throw new BusinessException(__('Nothing was sold from this consignment since the last settlement.'));
            }
            $grni = Account::query()->where('system_key', 'grni')->value('id');
            $si = app(PurchasingService::class)->createSupplierInvoice([
                'supplier_id' => $wh->consignor_supplier_id, 'supplier_invoice_number' => $supplierInvoiceNo ?: __('Consignment').' '.now()->format('Y-m-d'),
                'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays($wh->consignor->payment_terms_days ?? 30)->toDateString(),
                'notes' => __('Consignment settlement').' — '.$wh->name,
            ], array_map(fn ($s) => ['part_id' => $s['part']->id, 'account_id' => $grni, 'description' => $s['part']->displayName(),
                'qty' => $s['qty'], 'unit_cost' => Money::div($s['cost'], $s['qty'], 4), 'tax_rate' => 0], $sold));
            $settlement = ConsignmentSettlement::create([
                'warehouse_id' => $wh->id, 'supplier_id' => $wh->consignor_supplier_id, 'period_from' => $this->lastSettlementAt($wh), 'period_to' => now(),
                'qty' => Money::sum(array_column($sold, 'qty')), 'amount' => $si->subtotal, 'supplier_invoice_id' => $si->id, 'created_by' => auth()->id(),
            ]);
            foreach ($sold as $row) {
                DB::table('stock_movements')->whereIntegerInRaw('id', $row['movement_ids'])->update(['consignment_settlement_id' => $settlement->id]);
            }
            AuditLogger::log('consignment.settled', $wh, ['amount' => (string) $si->subtotal, 'invoice' => $si->number]);

            return $settlement;
        });
    }

    /** Give unsold parts back to the consignor. */
    public function returnToConsignor(Warehouse $wh, array $lines): string
    {
        $this->assertConsignment($wh);

        return DB::transaction(function () use ($wh, $lines) {
            $total = '0';
            foreach ($lines as $l) {
                if (! Money::isPositive($l['qty'] ?? 0)) {
                    continue;
                }
                $r = $this->stock->issue((int) $l['part_id'], $wh->id, $l['qty'], ['type' => 'consignment_return', 'reference' => $wh, 'notes' => __('Returned to :s', ['s' => $wh->consignor?->name])]);
                $total = Money::add($total, $r['total_cost']);
            }
            if (Money::isPositive($total)) {
                $this->posting->post(['date' => now()->toDateString(), 'description' => __('Consignment return').' '.$wh->name,
                    'source' => $wh, 'purpose' => 'consignment_return:'.$r['movement']->id,
                    'lines' => [['account' => 'grni', 'debit' => $total, 'party' => $wh->consignor], ['account' => 'inventory', 'credit' => $total]]]);
            }

            return $total;
        });
    }
}
