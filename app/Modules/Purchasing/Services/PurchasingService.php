<?php

namespace App\Modules\Purchasing\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\LandedCost;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\Rfq;
use App\Modules\Purchasing\Models\RfqSupplier;
use App\Modules\Purchasing\Models\SupplierInvoice;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Settings;
use App\Support\Totals;
use Illuminate\Support\Facades\DB;

/**
 * Procure-to-pay: PR -> RFQ -> PO -> GRN (partial) -> Supplier invoice -> Payment,
 * landed costs and purchase returns, all with automatic GL postings:
 *   GRN:              Dr Inventory          Cr GRNI
 *   Supplier invoice: Dr GRNI (+variance) Dr VAT input   Cr AP
 *   Landed cost:      Dr Inventory          Cr AP / GRNI
 *   Purchase return:  Dr AP                 Cr Inventory, Cr VAT input
 */
class PurchasingService
{
    public function __construct(protected StockService $stock, protected PostingService $posting) {}

    // ------------------------------------------------------ purchase requests

    public function createRequest(array $data, array $items): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $items) {
            $pr = PurchaseRequest::create($data + ['number' => DocumentNumber::next('purchase_request'), 'status' => 'submitted', 'requested_by' => auth()->id()]);
            foreach ($items as $i) {
                if (Money::isPositive($i['qty'] ?? 0)) {
                    $part = ! empty($i['part_id']) ? Part::query()->find($i['part_id']) : null;
                    $pr->items()->create(['part_id' => $part?->id, 'description' => $i['description'] ?? $part?->displayName() ?? '-', 'qty' => $i['qty'],
                        'estimated_cost' => $i['estimated_cost'] ?? $part?->purchase_price ?? 0]);
                }
            }

            return $pr;
        });
    }

    public function decideRequest(PurchaseRequest $pr, bool $approve): void
    {
        if ($pr->status !== 'submitted') {
            throw new BusinessException(__('Only submitted requests can be approved or rejected.'));
        }
        $pr->update(['status' => $approve ? 'approved' : 'rejected', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        AuditLogger::log('purchase_request.'.($approve ? 'approved' : 'rejected'), $pr);
    }

    // ------------------------------------------------------------------ RFQ

    public function createRfq(?PurchaseRequest $pr, array $supplierIds, array $items = [], ?string $dueDate = null): Rfq
    {
        return DB::transaction(function () use ($pr, $supplierIds, $items, $dueDate) {
            $rfq = Rfq::create(['number' => DocumentNumber::next('rfq'), 'purchase_request_id' => $pr?->id, 'status' => 'sent',
                'due_date' => $dueDate, 'created_by' => auth()->id()]);
            $items = $items ?: ($pr ? $pr->items->map(fn ($i) => ['part_id' => $i->part_id, 'description' => $i->description, 'qty' => $i->qty])->all() : []);
            foreach ($items as $i) {
                $rfq->items()->create(['part_id' => $i['part_id'] ?? null, 'description' => $i['description'] ?? '-', 'qty' => $i['qty']]);
            }
            foreach (array_unique($supplierIds) as $sid) {
                $rfq->suppliers()->create(['supplier_id' => $sid, 'status' => 'invited']);
            }

            return $rfq;
        });
    }

    /** @param array<int,array{unit_price:mixed,lead_days?:int}> $prices keyed by rfq_item_id */
    public function recordQuote(RfqSupplier $rs, array $prices, ?int $deliveryDays = null): void
    {
        DB::transaction(function () use ($rs, $prices, $deliveryDays) {
            $rs->quotes()->delete();
            $total = '0';
            foreach ($rs->rfq->items as $item) {
                if (! isset($prices[$item->id]) || $prices[$item->id]['unit_price'] === '' || $prices[$item->id]['unit_price'] === null) {
                    continue;
                }
                $rs->quotes()->create(['rfq_item_id' => $item->id, 'unit_price' => $prices[$item->id]['unit_price'], 'lead_days' => $prices[$item->id]['lead_days'] ?? null]);
                $total = Money::add($total, Money::mul($item->qty, $prices[$item->id]['unit_price']));
            }
            $rs->update(['status' => 'quoted', 'quoted_at' => now(), 'total' => $total, 'delivery_days' => $deliveryDays]);
        });
    }

    /** Comparison matrix: lowest price per item highlighted by the view. */
    public function compare(Rfq $rfq): array
    {
        $rfq->load('items.part', 'suppliers.supplier', 'suppliers.quotes');
        $matrix = [];
        foreach ($rfq->items as $item) {
            $row = ['item' => $item, 'prices' => [], 'best' => null];
            foreach ($rfq->suppliers as $rs) {
                $q = $rs->quotes->firstWhere('rfq_item_id', $item->id);
                $row['prices'][$rs->id] = $q?->unit_price;
                if ($q && ($row['best'] === null || Money::cmp($q->unit_price, $row['prices'][$row['best']]) < 0)) {
                    $row['best'] = $rs->id;
                }
            }
            $matrix[] = $row;
        }

        return $matrix;
    }

    public function awardRfq(Rfq $rfq, RfqSupplier $winner, int $warehouseId): PurchaseOrder
    {
        return DB::transaction(function () use ($rfq, $winner, $warehouseId) {
            $items = [];
            foreach ($rfq->items as $item) {
                $q = $winner->quotes->firstWhere('rfq_item_id', $item->id);
                if ($q && $item->part_id) {
                    $items[] = ['part_id' => $item->part_id, 'qty' => $item->qty, 'unit_cost' => $q->unit_price, 'description' => $item->description];
                }
            }
            if (! $items) {
                throw new BusinessException(__('The selected supplier has not quoted any catalog item.'));
            }
            $winner->update(['status' => 'awarded']);
            $rfq->update(['status' => 'awarded', 'awarded_supplier_id' => $winner->supplier_id]);
            $po = $this->createOrder(['supplier_id' => $winner->supplier_id, 'warehouse_id' => $warehouseId, 'rfq_id' => $rfq->id,
                'purchase_request_id' => $rfq->purchase_request_id], $items);
            $rfq->purchaseRequest?->update(['status' => 'ordered']);

            return $po;
        });
    }

    // ------------------------------------------------------- purchase order

    public function createOrder(array $header, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($header, $items) {
            $po = PurchaseOrder::create($header + ['number' => DocumentNumber::next('purchase_order'), 'status' => 'draft',
                'order_date' => now()->toDateString(), 'created_by' => auth()->id()]);
            $this->syncOrderItems($po, $items);

            return $po->fresh('items');
        });
    }

    public function updateOrder(PurchaseOrder $po, array $header, array $items): PurchaseOrder
    {
        if (! in_array($po->status, ['draft', 'pending_approval'], true)) {
            throw new BusinessException(__('Approved purchase orders cannot be edited.'));
        }

        return DB::transaction(function () use ($po, $header, $items) {
            $po->fill(array_intersect_key($header, array_flip(['supplier_id', 'warehouse_id', 'expected_date', 'notes', 'terms'])))->save();
            $po->items()->delete();
            $this->syncOrderItems($po, $items);

            return $po->fresh('items');
        });
    }

    protected function syncOrderItems(PurchaseOrder $po, array $items): void
    {
        foreach ($items as $i) {
            if (! Money::isPositive($i['qty'] ?? 0) || empty($i['part_id'])) {
                continue;
            }
            $part = Part::query()->findOrFail($i['part_id']);
            $cost = $i['unit_cost'] ?? $part->purchase_price;
            $rate = $i['tax_rate'] ?? $part->tax_rate;
            $c = Totals::line($i['qty'], $cost, $i['discount'] ?? 0, $rate);
            $po->items()->create(['part_id' => $part->id, 'description' => $i['description'] ?? $part->displayName(), 'qty' => $i['qty'],
                'unit_cost' => $cost, 'discount' => $c['discount'], 'tax_rate' => $rate, 'tax_amount' => $c['tax'], 'line_total' => $c['net']]);
        }
        if ($po->items()->count() === 0) {
            throw new BusinessException(__('Add at least one item.'));
        }
        Totals::apply($po);
    }

    public function submitOrder(PurchaseOrder $po): void
    {
        if ($po->status !== 'draft') {
            throw new BusinessException(__('Only draft orders can be submitted.'));
        }
        $po->update(['status' => 'pending_approval']);
    }

    public function approveOrder(PurchaseOrder $po): void
    {
        if (! in_array($po->status, ['draft', 'pending_approval'], true)) {
            throw new BusinessException(__('This order cannot be approved.'));
        }
        $limit = Settings::get('purchasing.approval_limit', '0');
        if (Money::isPositive($limit) && Money::cmp($po->total, $limit) > 0 && ! auth()->user()?->can('purchase_orders.approve_unlimited')) {
            throw new BusinessException(__('Order total exceeds your approval limit (:l).', ['l' => money($limit)]));
        }
        $po->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        AuditLogger::log('purchase_order.approved', $po, ['total' => (string) $po->total]);
    }

    // ------------------------------------------------------- goods receipt

    /**
     * @param  array<int,array{qty:mixed,unit_cost?:mixed,batch_number?:string,serials?:string,expiry_date?:string,location_id?:int}>  $lines  keyed by purchase_order_item_id
     */
    public function receive(PurchaseOrder $po, array $lines, array $header = []): GoodsReceipt
    {
        return DB::transaction(function () use ($po, $lines, $header) {
            $po = PurchaseOrder::query()->whereKey($po->id)->lockForUpdate()->firstOrFail();
            if (! in_array($po->status, ['approved', 'partially_received'], true)) {
                throw new BusinessException(__('Only approved purchase orders can be received.'));
            }
            $grn = GoodsReceipt::create([
                'number' => DocumentNumber::next('goods_receipt'), 'purchase_order_id' => $po->id, 'supplier_id' => $po->supplier_id,
                'warehouse_id' => $header['warehouse_id'] ?? $po->warehouse_id, 'branch_id' => $po->branch_id,
                'received_at' => $header['received_at'] ?? now(), 'supplier_reference' => $header['supplier_reference'] ?? null,
                'notes' => $header['notes'] ?? null, 'status' => 'draft', 'created_by' => auth()->id(),
            ]);
            foreach ($po->items as $poi) {
                $l = $lines[$poi->id] ?? null;
                $qty = Money::round($l['qty'] ?? 0);
                if (! Money::isPositive($qty)) {
                    continue;
                }
                $open = Money::sub($poi->qty, $poi->received_qty);
                if (Money::cmp($qty, $open) > 0) {
                    throw new BusinessException(__('Received quantity for :p exceeds the open quantity (:q).', ['p' => $poi->description, 'q' => qty($open)]));
                }
                // unit cost net of line discount
                $unit = $l['unit_cost'] ?? Money::div(Money::sub(Money::mul($poi->qty, $poi->unit_cost), $poi->discount), $poi->qty, 4);
                $grn->items()->create([
                    'purchase_order_item_id' => $poi->id, 'part_id' => $poi->part_id, 'qty' => $qty, 'unit_cost' => $unit,
                    'batch_number' => $l['batch_number'] ?? null, 'serial_numbers' => $l['serials'] ?? null,
                    'expiry_date' => $l['expiry_date'] ?? null, 'location_id' => $l['location_id'] ?? null, 'line_total' => Money::mul($qty, $unit),
                ]);
            }
            if ($grn->items()->count() === 0) {
                throw new BusinessException(__('Enter at least one received quantity.'));
            }

            return $this->postReceipt($grn);
        });
    }

    public function postReceipt(GoodsReceipt $grn): GoodsReceipt
    {
        return DB::transaction(function () use ($grn) {
            $grn = GoodsReceipt::query()->whereKey($grn->id)->lockForUpdate()->firstOrFail();
            if ($grn->status === 'posted') {
                return $grn;
            }
            $total = '0';
            foreach ($grn->items as $item) {
                $serials = $item->serial_numbers ? preg_split('/[\s,;]+/', trim($item->serial_numbers), -1, PREG_SPLIT_NO_EMPTY) : [];
                $r = $this->stock->receive($item->part_id, $grn->warehouse_id, $item->qty, $item->unit_cost, [
                    'type' => 'purchase_receipt', 'reference' => $grn, 'batch_number' => $item->batch_number, 'serials' => $serials,
                    'expiry_date' => $item->expiry_date?->toDateString(), 'location_id' => $item->location_id,
                ]);
                $item->update(['batch_ids' => $r['batches']]);
                $total = Money::add($total, $r['total_cost']);
                if ($item->purchase_order_item_id) {
                    $poi = $item->orderItem()->lockForUpdate()->first();
                    $poi->update(['received_qty' => Money::add($poi->received_qty, $item->qty)]);
                }
                DB::table('part_supplier')->updateOrInsert(['part_id' => $item->part_id, 'supplier_id' => $grn->supplier_id],
                    ['last_price' => $item->unit_cost, 'updated_at' => now(), 'created_at' => now()]);
                \App\Modules\Inventory\Services\InventoryAnalyticsService::recordPrice($item->part_id, $grn->supplier_id, $item->unit_cost, $item->qty, 'grn', $grn->number, $grn->received_at->toDateString());
            }
            $je = $this->posting->post([
                'date' => $grn->received_at->toDateString(), 'description' => __('Goods receipt').' '.$grn->number,
                'source' => $grn, 'branch_id' => $grn->branch_id,
                'lines' => [['account' => 'inventory', 'debit' => $total], ['account' => 'grni', 'credit' => $total, 'party' => $grn->supplier]],
            ]);
            $grn->forceFill(['status' => 'posted', 'total' => $total, 'journal_entry_id' => $je->id, 'posted_at' => now(), 'posted_by' => auth()->id()])->save();
            if ($grn->purchase_order_id) {
                $po = $grn->order()->with('items')->first();
                $complete = $po->items->every(fn ($i) => Money::cmp($i->received_qty, $i->qty) >= 0);
                $po->update(['status' => $complete ? 'received' : 'partially_received']);
            }
            AuditLogger::log('goods_receipt.posted', $grn, ['total' => $total]);

            return $grn;
        });
    }

    // ---------------------------------------------------- supplier invoices

    /** Create a supplier invoice from a GRN (quantities & costs) or free lines. */
    public function createSupplierInvoice(array $header, array $items): SupplierInvoice
    {
        return DB::transaction(function () use ($header, $items) {
            $si = SupplierInvoice::create($header + ['number' => DocumentNumber::next('supplier_invoice'), 'status' => 'draft', 'created_by' => auth()->id()]);
            foreach ($items as $i) {
                if (! Money::isPositive($i['qty'] ?? 0)) {
                    continue;
                }
                $c = Totals::line($i['qty'], $i['unit_cost'], 0, $i['tax_rate'] ?? 0);
                $si->items()->create(['part_id' => $i['part_id'] ?? null, 'account_id' => $i['account_id'] ?? null, 'description' => $i['description'] ?? '-',
                    'qty' => $i['qty'], 'unit_cost' => $i['unit_cost'], 'tax_rate' => $i['tax_rate'] ?? 0, 'tax_amount' => $c['tax'], 'line_total' => $c['net']]);
            }
            $items = $si->items()->get();
            $sub = Money::sum($items->pluck('line_total'));
            $tax = Money::sum($items->pluck('tax_amount'));
            $si->update(['subtotal' => $sub, 'tax_total' => $tax, 'total' => Money::add($sub, $tax)]);

            return $si;
        });
    }

    public function invoiceFromReceipt(GoodsReceipt $grn, string $supplierInvoiceNo, ?string $date = null): SupplierInvoice
    {
        if (SupplierInvoice::query()->where('goods_receipt_id', $grn->id)->where('status', '!=', 'cancelled')->exists()) {
            throw new BusinessException(__('This goods receipt is already invoiced.'));
        }
        $items = $grn->items->map(fn ($i) => ['part_id' => $i->part_id, 'description' => $i->part->displayName(), 'qty' => $i->qty,
            'unit_cost' => $i->unit_cost, 'tax_rate' => $i->orderItem?->tax_rate ?? $i->part->tax_rate])->all();
        $date ??= now()->toDateString();

        return $this->createSupplierInvoice([
            'supplier_id' => $grn->supplier_id, 'purchase_order_id' => $grn->purchase_order_id, 'goods_receipt_id' => $grn->id,
            'supplier_invoice_number' => $supplierInvoiceNo, 'invoice_date' => $date, 'branch_id' => $grn->branch_id,
            'due_date' => now()->parse($date)->addDays($grn->supplier->payment_terms_days)->toDateString(),
        ], $items);
    }

    public function postSupplierInvoice(SupplierInvoice $si): SupplierInvoice
    {
        return DB::transaction(function () use ($si) {
            $si = SupplierInvoice::query()->whereKey($si->id)->lockForUpdate()->firstOrFail();
            if ($si->status !== 'draft') {
                return $si;
            }
            $lines = [['account' => 'ap', 'credit' => $si->total, 'party' => $si->supplier, 'description' => $si->supplier_invoice_number]];
            $lines[] = ['account' => 'vat_input', 'debit' => $si->tax_total];
            if ($si->goods_receipt_id) {
                // clear GRNI at receipt value; the difference is a price variance
                $grnValue = (string) $si->receipt->total;
                $lines[] = ['account' => 'grni', 'debit' => $grnValue, 'party' => $si->supplier];
                $lines[] = ['account' => 'price_variance', 'debit' => Money::sub($si->subtotal, $grnValue)];
            } else {
                foreach ($si->items as $item) {
                    $lines[] = ['account' => $item->account_id ?: ($item->part_id ? 'inventory' : 'general_expense'), 'debit' => $item->line_total];
                }
            }
            $je = $this->posting->post([
                'date' => $si->invoice_date->toDateString(), 'description' => __('Supplier invoice').' '.$si->number.' / '.$si->supplier_invoice_number,
                'source' => $si, 'branch_id' => $si->branch_id, 'lines' => $lines,
            ]);
            $si->forceFill(['status' => 'posted', 'journal_entry_id' => $je->id, 'posted_at' => now(), 'posted_by' => auth()->id()])->save();
            AuditLogger::log('supplier_invoice.posted', $si, ['total' => (string) $si->total]);

            return $si;
        });
    }

    // ---------------------------------------------------------- landed cost

    /**
     * Allocate additional costs (freight, customs...) over a posted GRN and
     * revalue the remaining stock of the received layers.
     */
    public function postLandedCost(LandedCost $lc, string $creditAccount = 'ap'): LandedCost
    {
        return DB::transaction(function () use ($lc, $creditAccount) {
            $lc = LandedCost::query()->whereKey($lc->id)->lockForUpdate()->firstOrFail();
            if ($lc->status === 'posted') {
                return $lc;
            }
            $grn = $lc->receipt()->with('items')->first();
            if ($grn->status !== 'posted') {
                throw new BusinessException(__('The goods receipt must be posted first.'));
            }
            $total = Money::sum($lc->lines()->pluck('amount'));
            if (! Money::isPositive($total)) {
                throw new BusinessException(__('Add landed cost amounts.'));
            }
            $basis = $lc->allocation_method === 'qty' ? Money::sum($grn->items->pluck('qty')) : Money::sum($grn->items->pluck('line_total'));
            $allocated = '0';
            $items = $grn->items->values();
            foreach ($items as $idx => $item) {
                $weight = $lc->allocation_method === 'qty' ? $item->qty : $item->line_total;
                $share = $idx === $items->count() - 1 ? Money::sub($total, $allocated) : Money::round(bcdiv(bcmul(Money::n($total), Money::n($weight), 10), Money::n($basis ?: 1), 10));
                $allocated = Money::add($allocated, $share);
                $perUnit = Money::div($share, $item->qty, 4);
                $item->update(['landed_cost_per_unit' => Money::add($item->landed_cost_per_unit, $perUnit)]);
                // revalue remaining layers and warehouse average
                foreach ((array) $item->batch_ids as $batchId) {
                    $b = StockBatch::query()->lockForUpdate()->find($batchId);
                    $b?->update(['unit_cost' => Money::round(Money::add($b->unit_cost, $perUnit), 4)]);
                }
                $level = StockLevel::query()->where('part_id', $item->part_id)->where('warehouse_id', $grn->warehouse_id)->lockForUpdate()->first();
                if ($level && Money::isPositive($level->qty_on_hand)) {
                    $value = Money::add(Money::mul($level->qty_on_hand, $level->avg_cost), $share);
                    $level->update(['avg_cost' => Money::div($value, $level->qty_on_hand, 4)]);
                }
            }
            $je = $this->posting->post([
                'date' => $lc->date->toDateString(), 'description' => __('Landed cost').' '.$lc->number.' / '.$grn->number,
                'source' => $lc, 'lines' => [
                    ['account' => 'inventory', 'debit' => $total],
                    ['account' => $creditAccount, 'credit' => $total, 'party' => $lc->supplier ?? $grn->supplier],
                ],
            ]);
            $lc->forceFill(['status' => 'posted', 'total' => $total, 'journal_entry_id' => $je->id, 'posted_at' => now()])->save();

            return $lc;
        });
    }

    // ------------------------------------------------------ purchase return

    public function postReturn(PurchaseReturn $pr): PurchaseReturn
    {
        return DB::transaction(function () use ($pr) {
            $pr = PurchaseReturn::query()->whereKey($pr->id)->lockForUpdate()->firstOrFail();
            if ($pr->status === 'posted') {
                return $pr;
            }
            $cost = '0';
            foreach ($pr->items as $item) {
                if ($item->goods_receipt_item_id) {
                    $gi = $item->receiptItem()->lockForUpdate()->first();
                    if (Money::cmp(Money::add($gi->returned_qty, $item->qty), $gi->qty) > 0) {
                        throw new BusinessException(__('Return quantity exceeds received quantity.'));
                    }
                    $gi->update(['returned_qty' => Money::add($gi->returned_qty, $item->qty)]);
                }
                $r = $this->stock->issue($item->part_id, $pr->warehouse_id, $item->qty, ['type' => 'purchase_return', 'reference' => $pr]);
                $cost = Money::add($cost, $r['total_cost']);
            }
            Totals::apply($pr);
            $pr->refresh();
            $je = $this->posting->post([
                'date' => $pr->date->toDateString(), 'description' => __('Purchase return').' '.$pr->number, 'source' => $pr,
                'lines' => [
                    ['account' => 'ap', 'debit' => $pr->total, 'party' => $pr->supplier],
                    ['account' => 'inventory', 'credit' => $cost],
                    ['account' => 'price_variance', 'credit' => Money::sub($pr->subtotal, $cost)],
                    ['account' => 'vat_input', 'credit' => $pr->tax_total],
                ],
            ]);
            $pr->forceFill(['status' => 'posted', 'journal_entry_id' => $je->id, 'posted_at' => now()])->save();

            return $pr;
        });
    }

    /** Supplier KPI: on-time delivery %, avg lead days, spend. */
    public function supplierPerformance(int $supplierId): array
    {
        $orders = PurchaseOrder::query()->where('supplier_id', $supplierId)->whereIn('status', ['received', 'partially_received', 'closed'])->with('receipts')->get();
        $onTime = $orders->filter(fn ($o) => $o->expected_date && $o->receipts->min('received_at') && $o->receipts->min('received_at')->lte($o->expected_date->endOfDay()))->count();
        $lead = $orders->map(fn ($o) => $o->receipts->min('received_at') ? $o->order_date->diffInDays($o->receipts->min('received_at')) : null)->filter();

        return [
            'orders' => $orders->count(),
            'on_time_rate' => $orders->count() ? round($onTime * 100 / $orders->count(), 1) : null,
            'avg_lead_days' => $lead->count() ? round($lead->avg(), 1) : null,
            'spend' => Money::sum(SupplierInvoice::query()->where('supplier_id', $supplierId)->whereIn('status', ['posted', 'partially_paid', 'paid'])->pluck('total')),
            'returns' => PurchaseReturn::query()->where('supplier_id', $supplierId)->where('status', 'posted')->count(),
        ];
    }
}
