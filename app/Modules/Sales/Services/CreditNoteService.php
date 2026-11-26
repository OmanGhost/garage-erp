<?php

namespace App\Modules\Sales\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Models\CreditNote;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Models\InvoiceItem;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Totals;
use Illuminate\Support\Facades\DB;

/**
 * Sales returns / credit notes: reverse revenue & VAT, optionally restock at
 * the original cost, never exceeding the invoiced (not yet returned) quantity.
 */
class CreditNoteService
{
    public function __construct(protected StockService $stock, protected PostingService $posting, protected PaymentService $payments) {}

    /** @param array<int,mixed> $quantities [invoice_item_id => qty] */
    public function create(Invoice $invoice, array $quantities, bool $restock = true, ?string $reason = null, ?int $warehouseId = null): CreditNote
    {
        if (! $invoice->isPosted()) {
            throw new BusinessException(__('Credit notes can only be issued for posted invoices.'));
        }

        return DB::transaction(function () use ($invoice, $quantities, $restock, $reason, $warehouseId) {
            $cn = CreditNote::create([
                'invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id, 'branch_id' => $invoice->branch_id,
                'date' => now()->toDateString(), 'reason' => $reason, 'restock' => $restock,
                'warehouse_id' => $warehouseId ?? $invoice->warehouse_id, 'status' => 'draft', 'created_by' => auth()->id(),
            ]);
            foreach ($quantities as $itemId => $qty) {
                $qty = Money::round($qty);
                if (! Money::isPositive($qty)) {
                    continue;
                }
                $item = InvoiceItem::query()->where('invoice_id', $invoice->id)->findOrFail($itemId);
                $max = Money::sub($item->qty, $item->returned_qty);
                if (Money::cmp($qty, $max) > 0) {
                    throw new BusinessException(__('Return quantity for :d exceeds the remaining invoiced quantity (:q).', ['d' => $item->description, 'q' => qty($max)]));
                }
                // value the return at the price actually charged: the line's share of the invoice discount
                // (the last return of a line takes whatever discount is left, so rounding never drifts)
                $discountReturned = Money::sum(\App\Modules\Sales\Models\CreditNoteItem::query()->where('invoice_item_id', $item->id)
                    ->whereHas('creditNote', fn ($q) => $q->where('status', 'posted'))->pluck('discount'));
                $discount = Money::cmp($qty, $max) === 0
                    ? Money::max(0, Money::sub($item->discount, $discountReturned))
                    : Money::round(bcmul(Money::n($item->discount), bcdiv(Money::n($qty), Money::n($item->qty), 10), 10));
                $c = Totals::line($qty, $item->unit_price, $discount, $item->tax_rate);
                $cn->items()->create([
                    'invoice_item_id' => $item->id, 'part_id' => $item->part_id, 'description' => $item->description,
                    'qty' => $qty, 'unit_price' => $item->unit_price, 'discount' => $c['discount'], 'tax_rate' => $item->tax_rate,
                    'tax_amount' => $c['tax'], 'line_total' => $c['net'], 'unit_cost' => $item->unit_cost,
                ]);
            }
            if ($cn->items()->count() === 0) {
                throw new BusinessException(__('Select at least one item to return.'));
            }
            $items = $cn->items()->get();
            $cn->update([
                'subtotal' => Money::sum($items->pluck('line_total')),
                'tax_total' => Money::sum($items->pluck('tax_amount')),
                'total' => Money::add(Money::sum($items->pluck('line_total')), Money::sum($items->pluck('tax_amount'))),
            ]);

            return $cn->fresh('items');
        });
    }

    public function post(CreditNote $cn, ?int $refundBankAccountId = null): CreditNote
    {
        return DB::transaction(function () use ($cn, $refundBankAccountId) {
            $cn = CreditNote::query()->whereKey($cn->id)->lockForUpdate()->firstOrFail();
            if ($cn->status === 'posted') {
                return $cn;
            }
            $cn->load('items.invoiceItem.part', 'invoice.jobCard.department', 'customer');
            $other = $cost = '0';
            $parts = $services = $costByCc = []; // reversed on the same cost centers as the original invoice lines
            $invoices = app(InvoiceService::class);
            foreach ($cn->items as $ci) {
                $ii = $ci->invoiceItem;
                $ii->returned_qty = Money::add($ii->returned_qty, $ci->qty);
                if (Money::cmp($ii->returned_qty, $ii->qty) > 0) {
                    throw new BusinessException(__('Return quantity exceeds invoiced quantity.'));
                }
                $ii->save();
                $cc = $invoices->costCenterFor($cn->invoice, $ii) ?? 0;
                match ($ii->item_type) {
                    'part' => $parts[$cc] = Money::add($parts[$cc] ?? '0', $ci->line_total),
                    'service', 'labor' => $services[$cc] = Money::add($services[$cc] ?? '0', $ci->line_total),
                    default => $other = Money::add($other, $ci->line_total),
                };
                if ($cn->restock && $ci->part_id && ! $ii->part?->is_service_item) {
                    $wh = $cn->warehouse_id ?: $ii->warehouse_id;
                    if ($wh) {
                        $this->stock->receive($ci->part_id, $wh, $ci->qty, $ci->unit_cost, ['type' => 'sales_return', 'reference' => $cn, 'update_purchase_price' => false]);
                        $cost = Money::add($cost, Money::mul($ci->qty, $ci->unit_cost));
                        $costByCc[$cc] = Money::add($costByCc[$cc] ?? '0', Money::mul($ci->qty, $ci->unit_cost));
                    }
                }
            }
            $cn->number = DocumentNumber::next('credit_note');
            $je = $this->posting->post([
                'date' => $cn->date->toDateString(),
                'description' => __('Credit note').' '.$cn->number.' / '.$cn->invoice->number,
                'source' => $cn,
                'branch_id' => $cn->branch_id,
                'lines' => array_merge(
                    array_map(fn ($cc, $amt) => ['account' => 'sales_parts', 'debit' => $amt, 'cost_center_id' => $cc ?: null], array_keys($parts), $parts),
                    array_map(fn ($cc, $amt) => ['account' => 'sales_services', 'debit' => $amt, 'cost_center_id' => $cc ?: null], array_keys($services), $services),
                    [
                        ['account' => 'other_revenue', 'debit' => $other],
                        ['account' => 'vat_output', 'debit' => $cn->tax_total],
                        ['account' => 'ar', 'credit' => $cn->total, 'party' => $cn->customer, 'description' => $cn->number],
                        ['account' => 'inventory', 'debit' => $cost],
                    ],
                    array_map(fn ($cc, $amt) => ['account' => 'cogs', 'credit' => $amt, 'cost_center_id' => $cc ?: null], array_keys($costByCc), $costByCc),
                ),
            ]);
            $cn->forceFill(['status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id(), 'journal_entry_id' => $je->id, 'cost_total' => $cost])->save();

            // settle invoice status
            $inv = Invoice::query()->whereKey($cn->invoice_id)->lockForUpdate()->first();
            if ($inv && Money::cmp($inv->balanceDue(), 0) <= 0 && $inv->status !== 'paid') {
                $inv->status = 'paid';
                $inv->save();
            }

            // refund only what the customer actually paid beyond the remaining balance
            if ($refundBankAccountId) {
                $refund = Money::min($cn->total, Money::max(0, Money::neg($cn->customer->balance())));
                if (Money::isPositive($refund)) {
                    $payment = $this->payments->refundCustomer($cn->customer, $refund, $refundBankAccountId, ['notes' => __('Refund for').' '.$cn->number]);
                    $cn->forceFill(['refunded_amount' => $refund, 'refund_payment_id' => $payment->id])->save();
                }
            }
            AuditLogger::log('credit_note.posted', $cn, ['total' => (string) $cn->total]);

            return $cn;
        });
    }
}
