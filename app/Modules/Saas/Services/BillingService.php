<?php

namespace App\Modules\Saas\Services;

use App\Exceptions\BusinessException;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Plan;
use App\Modules\Core\Models\Subscription;
use App\Modules\Core\Models\SubscriptionInvoice;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\DB;

/**
 * Platform billing: tax invoices to client companies (subscription renewals and custom charges), payments and
 * cancellations. Numbers: SUB-YYYY-000001 (platform wide, yearly).
 */
class BillingService
{
    public function nextNumber(): string
    {
        $year = now()->format('Y');
        $last = SubscriptionInvoice::query()->where('number', 'like', "SUB-$year-%")->lockForUpdate()->orderByDesc('number')->value('number');
        $n = $last ? ((int) substr($last, -6)) + 1 : 1;

        return sprintf('SUB-%s-%06d', $year, $n);
    }

    /**
     * @param  array<int, array{description: string, qty: mixed, unit_price: mixed}>  $items
     * @param  array{tax_rate?: mixed, discount?: mixed, issue_date?: string, due_date?: string, period_start?: ?string, period_end?: ?string,
     *               subscription_id?: ?int, notes?: ?string, paid?: bool, payment_method?: ?string, payment_reference?: ?string}  $opt
     */
    public function issue(Company $company, array $items, array $opt = []): SubscriptionInvoice
    {
        $items = array_values(array_filter(array_map(fn ($i) => [
            'description' => trim((string) ($i['description'] ?? '')),
            'qty' => Money::round($i['qty'] ?? 1),
            'unit_price' => Money::round($i['unit_price'] ?? 0),
        ], $items), fn ($i) => $i['description'] !== '' && Money::isPositive($i['qty'])));
        if (! $items) {
            throw new BusinessException(__('Add at least one invoice line.'));
        }
        $subtotal = '0';
        foreach ($items as $i) {
            $subtotal = Money::add($subtotal, Money::mul($i['qty'], $i['unit_price']));
        }
        $discount = Money::min(Money::round($opt['discount'] ?? 0), $subtotal);
        $taxRate = (string) ($opt['tax_rate'] ?? SiteSettings::get('billing_tax_rate', 0));
        $taxable = Money::sub($subtotal, $discount);
        $tax = Money::round(Money::div(Money::mul($taxable, $taxRate), 100));
        $total = Money::add($taxable, $tax);
        $issue = $opt['issue_date'] ?? now()->toDateString();
        $paid = (bool) ($opt['paid'] ?? false);

        return DB::transaction(function () use ($company, $items, $opt, $subtotal, $discount, $taxRate, $tax, $total, $issue, $paid) {
            $inv = SubscriptionInvoice::create([
                'company_id' => $company->id, 'subscription_id' => $opt['subscription_id'] ?? null, 'number' => $this->nextNumber(),
                'issue_date' => $issue, 'due_date' => $opt['due_date'] ?? now()->parse($issue)->addDays((int) SiteSettings::get('billing_due_days', 7))->toDateString(),
                'period_start' => $opt['period_start'] ?? null, 'period_end' => $opt['period_end'] ?? null,
                'items' => $items, 'subtotal' => $subtotal, 'discount' => $discount, 'tax_rate' => $taxRate, 'tax_amount' => $tax, 'amount' => $total,
                'status' => $paid ? 'paid' : 'unpaid', 'paid_at' => $paid ? now() : null,
                'payment_method' => $paid ? ($opt['payment_method'] ?? null) : null, 'payment_reference' => $paid ? ($opt['payment_reference'] ?? null) : null,
                'notes' => $opt['notes'] ?? null, 'created_by' => auth()->id(),
            ]);
            AuditLogger::log('saas.invoice_issued', $company, ['invoice' => $inv->number, 'amount' => (string) $total]);

            return $inv;
        });
    }

    /** Extend the subscription by one cycle and invoice it. */
    public function renew(Company $company, Plan $plan, string $cycle, array $opt = []): SubscriptionInvoice
    {
        return DB::transaction(function () use ($company, $plan, $cycle, $opt) {
            $start = $company->subscription_ends_at && $company->subscription_ends_at->isFuture() ? $company->subscription_ends_at->copy() : now();
            $end = $cycle === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth();
            $amount = $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
            $sub = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'billing_cycle' => $cycle, 'status' => 'active',
                'starts_at' => $start->toDateString(), 'ends_at' => $end->toDateString(), 'amount' => $amount]);
            $label = __('Subscription — :p (:c) :a → :b', ['p' => $plan->name, 'c' => status_label($cycle), 'a' => $start->format('Y-m-d'), 'b' => $end->format('Y-m-d')]);
            $items = array_merge([['description' => $label, 'qty' => 1, 'unit_price' => $amount]], $opt['extra_items'] ?? []);
            $inv = $this->issue($company, $items, $opt + ['subscription_id' => $sub->id, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString()]);
            $company->update(['plan_id' => $plan->id, 'subscription_status' => 'active', 'subscription_ends_at' => $end, 'is_active' => true]);

            return $inv;
        });
    }

    public function markPaid(SubscriptionInvoice $inv, ?string $method, ?string $reference, ?string $date = null): void
    {
        if ($inv->status !== 'unpaid') {
            throw new BusinessException(__('Only unpaid invoices can be paid.'));
        }
        $inv->forceFill(['status' => 'paid', 'paid_at' => $date ? now()->parse($date) : now(), 'payment_method' => $method, 'payment_reference' => $reference])->save();
        AuditLogger::log('saas.subscription_invoice_paid', $inv->company, ['invoice' => $inv->number, 'amount' => (string) $inv->amount, 'method' => $method]);
    }

    public function cancel(SubscriptionInvoice $inv, string $reason): void
    {
        if ($inv->status !== 'unpaid') {
            throw new BusinessException(__('Only unpaid invoices can be cancelled.'));
        }
        $inv->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
        AuditLogger::log('saas.subscription_invoice_cancelled', $inv->company, ['invoice' => $inv->number, 'reason' => $reason]);
    }
}
