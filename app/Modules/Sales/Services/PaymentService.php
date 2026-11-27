<?php

namespace App\Modules\Sales\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Models\SupplierInvoice;
use App\Modules\Sales\Events\PaymentReceived;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Models\Payment;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Receipts (customer -> us) and disbursements (us -> supplier / customer refunds)
 * with document allocation and automatic GL posting.
 */
class PaymentService
{
    public function __construct(protected PostingService $posting) {}

    /**
     * @param  array<int,mixed>|null  $allocations  [invoice_id => amount]; null = auto allocate oldest first
     */
    public function receiveFromCustomer(Customer $customer, mixed $amount, int $bankAccountId, string $method = 'cash', ?array $allocations = null, array $opt = []): Payment
    {
        $amount = Money::round($amount);
        if (! Money::isPositive($amount)) {
            throw new BusinessException(__('Amount must be greater than zero.'));
        }

        return DB::transaction(function () use ($customer, $amount, $bankAccountId, $method, $allocations, $opt) {
            $bank = BankAccount::query()->findOrFail($bankAccountId);
            $payment = Payment::create([
                'number' => DocumentNumber::next('payment_in'),
                'direction' => 'in', 'party_type' => 'customer', 'party_id' => $customer->id,
                'method' => $method, 'bank_account_id' => $bank->id, 'amount' => $amount,
                'date' => $opt['date'] ?? now()->toDateString(), 'reference' => $opt['reference'] ?? null,
                'cheque_number' => $opt['cheque_number'] ?? null, 'cheque_date' => $opt['cheque_date'] ?? null,
                'notes' => $opt['notes'] ?? null, 'status' => 'posted', 'pos_session_id' => $opt['pos_session_id'] ?? null,
                'gateway' => $opt['gateway'] ?? null, 'gateway_reference' => $opt['gateway_reference'] ?? null,
                'branch_id' => $opt['branch_id'] ?? null, 'created_by' => auth()->id(),
            ]);

            $this->allocateToInvoices($payment, $customer, $amount, $allocations);

            $je = $this->posting->post([
                'date' => $payment->date->toDateString(),
                'description' => __('Receipt').' '.$payment->number.' - '.$customer->name,
                'source' => $payment,
                'branch_id' => $payment->branch_id,
                'lines' => [
                    ['account' => $bank->account_id, 'debit' => $amount],
                    ['account' => 'ar', 'credit' => $amount, 'party' => $customer, 'description' => $payment->number],
                ],
            ]);
            $payment->forceFill(['journal_entry_id' => $je->id])->save();
            AuditLogger::log('payment.received', $payment, ['amount' => $amount]);
            DB::afterCommit(fn () => event(new PaymentReceived($payment)));

            return $payment;
        });
    }

    /**
     * Settle an invoice without money changing hands: from loyalty points or a gift card. Booked
     * Dr <liability> / Cr receivables and allocated like any receipt (no bank account).
     */
    public function settleFromLiability(Invoice $invoice, mixed $amount, string $method, string $liabilityKey, ?string $reference = null): Payment
    {
        $amount = Money::round($amount);
        if (! Money::isPositive($amount)) {
            throw new BusinessException(__('Amount must be greater than zero.'));
        }

        return DB::transaction(function () use ($invoice, $amount, $method, $liabilityKey, $reference) {
            $customer = $invoice->customer;
            $payment = Payment::create([
                'number' => DocumentNumber::next('payment_in'), 'direction' => 'in', 'party_type' => 'customer', 'party_id' => $customer->id,
                'method' => $method, 'bank_account_id' => null, 'amount' => $amount, 'date' => now()->toDateString(), 'reference' => $reference,
                'status' => 'posted', 'branch_id' => $invoice->branch_id, 'created_by' => auth()->id(),
            ]);
            $this->allocateToInvoices($payment, $customer, $amount, [$invoice->id => $amount]);
            $je = $this->posting->post([
                'date' => $payment->date->toDateString(), 'description' => __('Receipt').' '.$payment->number.' - '.status_label($method),
                'source' => $payment, 'branch_id' => $payment->branch_id,
                'lines' => [['account' => $liabilityKey, 'debit' => $amount], ['account' => 'ar', 'credit' => $amount, 'party' => $customer, 'description' => $payment->number]],
            ]);
            $payment->forceFill(['journal_entry_id' => $je->id])->save();
            AuditLogger::log('payment.received', $payment, ['amount' => $amount, 'method' => $method]);

            return $payment;
        });
    }

    protected function allocateToInvoices(Payment $payment, Customer $customer, string $amount, ?array $allocations): void
    {
        $left = $amount;
        if ($allocations === null) {
            $open = Invoice::query()->where('customer_id', $customer->id)->whereIn('status', ['posted', 'partially_paid'])
                ->orderBy('issue_date')->orderBy('id')->lockForUpdate()->get();
            $allocations = [];
            foreach ($open as $inv) {
                if (! Money::isPositive($left)) {
                    break;
                }
                $take = Money::min($left, $inv->balanceDue());
                if (Money::isPositive($take)) {
                    $allocations[$inv->id] = $take;
                    $left = Money::sub($left, $take);
                }
            }
            $left = $amount;
        }
        foreach ($allocations as $invoiceId => $alloc) {
            $alloc = Money::round($alloc);
            if (! Money::isPositive($alloc)) {
                continue;
            }
            $inv = Invoice::query()->whereKey($invoiceId)->where('customer_id', $customer->id)->lockForUpdate()->firstOrFail();
            if (! in_array($inv->status, ['posted', 'partially_paid'], true)) {
                throw new BusinessException(__('Invoice :n is not open for payment.', ['n' => $inv->displayNumber()]));
            }
            if (Money::cmp($alloc, $inv->balanceDue()) > 0) {
                throw new BusinessException(__('Allocation exceeds the balance of invoice :n.', ['n' => $inv->number]));
            }
            if (Money::cmp($alloc, $left) > 0) {
                throw new BusinessException(__('Allocations exceed the payment amount.'));
            }
            $payment->allocations()->create(['allocatable_type' => $inv->getMorphClass(), 'allocatable_id' => $inv->id, 'amount' => $alloc]);
            $this->applyToInvoice($inv, $alloc);
            $left = Money::sub($left, $alloc);
        }
    }

    public function applyToInvoice(Invoice $inv, string $amount): void
    {
        $inv->paid_amount = Money::add($inv->paid_amount, $amount);
        $inv->status = Money::cmp($inv->balanceDue(), 0) <= 0 ? 'paid' : 'partially_paid';
        $inv->save();
    }

    /** Pay a supplier (AP) with optional allocation to supplier invoices. */
    public function payToSupplier(Supplier $supplier, mixed $amount, int $bankAccountId, string $method = 'bank_transfer', ?array $allocations = null, array $opt = []): Payment
    {
        $amount = Money::round($amount);
        if (! Money::isPositive($amount)) {
            throw new BusinessException(__('Amount must be greater than zero.'));
        }

        return DB::transaction(function () use ($supplier, $amount, $bankAccountId, $method, $allocations, $opt) {
            $bank = BankAccount::query()->findOrFail($bankAccountId);
            $payment = Payment::create([
                'number' => DocumentNumber::next('payment_out'), 'direction' => 'out',
                'party_type' => 'supplier', 'party_id' => $supplier->id, 'method' => $method,
                'bank_account_id' => $bank->id, 'amount' => $amount, 'date' => $opt['date'] ?? now()->toDateString(),
                'reference' => $opt['reference'] ?? null, 'cheque_number' => $opt['cheque_number'] ?? null,
                'cheque_date' => $opt['cheque_date'] ?? null, 'notes' => $opt['notes'] ?? null, 'status' => 'posted',
                'created_by' => auth()->id(),
            ]);
            $left = $amount;
            if ($allocations === null) {
                $allocations = [];
                foreach (SupplierInvoice::query()->where('supplier_id', $supplier->id)->whereIn('status', ['posted', 'partially_paid'])->orderBy('invoice_date')->lockForUpdate()->get() as $si) {
                    if (! Money::isPositive($left)) {
                        break;
                    }
                    $take = Money::min($left, $si->balanceDue());
                    $allocations[$si->id] = $take;
                    $left = Money::sub($left, $take);
                }
                $left = $amount;
            }
            foreach ($allocations as $id => $alloc) {
                $alloc = Money::round($alloc);
                if (! Money::isPositive($alloc)) {
                    continue;
                }
                $si = SupplierInvoice::query()->whereKey($id)->where('supplier_id', $supplier->id)->lockForUpdate()->firstOrFail();
                if (Money::cmp($alloc, $si->balanceDue()) > 0 || Money::cmp($alloc, $left) > 0) {
                    throw new BusinessException(__('Allocation exceeds the open balance.'));
                }
                $payment->allocations()->create(['allocatable_type' => $si->getMorphClass(), 'allocatable_id' => $si->id, 'amount' => $alloc]);
                $si->paid_amount = Money::add($si->paid_amount, $alloc);
                $si->status = Money::cmp($si->balanceDue(), 0) <= 0 ? 'paid' : 'partially_paid';
                $si->save();
                $left = Money::sub($left, $alloc);
            }
            $je = $this->posting->post([
                'date' => $payment->date->toDateString(),
                'description' => __('Supplier payment').' '.$payment->number.' - '.$supplier->name,
                'source' => $payment,
                'lines' => [
                    ['account' => 'ap', 'debit' => $amount, 'party' => $supplier, 'description' => $payment->number],
                    ['account' => $bank->account_id, 'credit' => $amount],
                ],
            ]);
            $payment->forceFill(['journal_entry_id' => $je->id])->save();
            AuditLogger::log('payment.paid', $payment, ['amount' => $amount]);

            return $payment;
        });
    }

    /** Refund money to a customer (e.g. after a credit note). */
    public function refundCustomer(Customer $customer, mixed $amount, int $bankAccountId, array $opt = []): Payment
    {
        return DB::transaction(function () use ($customer, $amount, $bankAccountId, $opt) {
            $bank = BankAccount::query()->findOrFail($bankAccountId);
            $payment = Payment::create([
                'number' => DocumentNumber::next('payment_out'), 'direction' => 'out', 'party_type' => 'customer', 'party_id' => $customer->id,
                'method' => $opt['method'] ?? 'cash', 'bank_account_id' => $bank->id, 'amount' => Money::round($amount),
                'date' => now()->toDateString(), 'notes' => $opt['notes'] ?? __('Customer refund'), 'status' => 'posted',
                'pos_session_id' => $opt['pos_session_id'] ?? null, 'created_by' => auth()->id(),
            ]);
            $je = $this->posting->post([
                'date' => $payment->date->toDateString(), 'description' => __('Customer refund').' '.$payment->number, 'source' => $payment,
                'lines' => [
                    ['account' => 'ar', 'debit' => $amount, 'party' => $customer],
                    ['account' => $bank->account_id, 'credit' => $amount],
                ],
            ]);
            $payment->forceFill(['journal_entry_id' => $je->id])->save();

            return $payment;
        });
    }

    /** Cancel a payment: reverse its journal and un-apply allocations. */
    public function cancel(Payment $payment, string $reason = ''): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'cancelled') {
                return;
            }
            foreach ($payment->allocations as $a) {
                $doc = $a->allocatable()->lockForUpdate()->first();
                if ($doc) {
                    $doc->paid_amount = Money::max(0, Money::sub($doc->paid_amount, $a->amount));
                    $doc->status = Money::isZero($doc->paid_amount) ? 'posted' : 'partially_paid';
                    $doc->save();
                }
            }
            if ($payment->journal) {
                $this->posting->reverse($payment->journal, null, $reason ?: __('Payment cancelled'));
            }
            $payment->status = 'cancelled';
            $payment->save();
            AuditLogger::log('payment.cancelled', $payment, ['reason' => $reason]);
            // points / gift card balance come back when their payment is cancelled
            if ($payment->method === 'loyalty') {
                app(\App\Modules\Loyalty\Services\LoyaltyService::class)->refundPayment($payment);
            } elseif ($payment->method === 'gift_card') {
                app(\App\Modules\Loyalty\Services\GiftCardService::class)->refundPayment($payment);
            }
        });
    }
}
