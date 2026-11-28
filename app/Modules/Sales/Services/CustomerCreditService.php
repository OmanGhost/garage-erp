<?php

namespace App\Modules\Sales\Services;

use App\Exceptions\BusinessException;
use App\Modules\CRM\Models\Customer;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Models\Payment;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Paying an invoice from the customer's credit balance (advance payments, over-payments, credit notes not refunded).
 *
 * The money is already in the books — the customer's receivable account is in credit — so applying it moves no cash
 * and makes no journal entry: a "balance" receipt is allocated to the invoice, which marks it paid; in the ledger the
 * new invoice and the existing credit simply offset each other.
 *
 * Available credit = what the customer's open invoices still owe − the receivable balance in the ledger
 * (i.e. the credit not yet matched to any invoice).
 */
class CustomerCreditService
{
    public function __construct(protected PaymentService $payments) {}

    public function available(Customer $customer, ?int $exceptInvoiceId = null): string
    {
        if ($customer->isWalkIn()) {
            return '0';
        }
        $openDue = '0';
        foreach (Invoice::query()->with('creditNotes')->where('customer_id', $customer->id)->whereIn('status', ['posted', 'partially_paid'])->get() as $inv) {
            $openDue = Money::add($openDue, $inv->balanceDue());
        }

        return Money::max('0', Money::sub($openDue, $customer->balance()));
    }

    public function apply(Invoice $invoice, mixed $amount = null, ?int $posSessionId = null): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $posSessionId) {
            $invoice = Invoice::query()->with('customer')->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $customer = Customer::query()->whereKey($invoice->customer_id)->lockForUpdate()->firstOrFail();
            if (! in_array($invoice->status, ['posted', 'partially_paid'], true)) {
                throw new BusinessException(__('Invoice :n is not open for payment.', ['n' => $invoice->displayNumber()]));
            }
            $available = $this->available($customer);
            $amount = Money::round($amount ?? Money::min($available, $invoice->balanceDue()));
            if (! Money::isPositive($amount)) {
                throw new BusinessException(__('The customer has no credit balance to use.'));
            }
            if (Money::cmp($amount, $available) > 0) {
                throw new BusinessException(__('The customer credit balance is only :a.', ['a' => money($available)]));
            }
            if (Money::cmp($amount, $invoice->balanceDue()) > 0) {
                throw new BusinessException(__('Allocation exceeds the balance of invoice :n.', ['n' => $invoice->displayNumber()]));
            }
            $payment = Payment::create([
                'number' => DocumentNumber::next('payment_in'), 'direction' => 'in', 'party_type' => 'customer', 'party_id' => $customer->id,
                'method' => 'balance', 'bank_account_id' => null, 'amount' => $amount, 'net_amount' => $amount, 'date' => now()->toDateString(),
                'notes' => __('Paid from the customer credit balance'), 'status' => 'posted', 'pos_session_id' => $posSessionId,
                'branch_id' => $invoice->branch_id, 'created_by' => auth()->id(),
            ]);
            $payment->allocations()->create(['allocatable_type' => $invoice->getMorphClass(), 'allocatable_id' => $invoice->id, 'amount' => $amount]);
            $this->payments->applyToInvoice($invoice, $amount);
            AuditLogger::log('payment.from_balance', $payment, ['invoice' => $invoice->number, 'amount' => $amount]);

            return $payment;
        });
    }
}
