<?php

namespace App\Modules\Loyalty\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Loyalty\Models\LoyaltyTransaction;
use App\Modules\Sales\Models\CreditNote;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Services\PaymentService;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Customer loyalty: points earned on posted invoices (net of VAT, multiplied by the membership tier), a referral
 * bonus for the customer who referred a new one, redemption as a payment on an invoice, reversal on returns and
 * expiry after a period without activity. Accounting: earning books Dr Loyalty expense / Cr Loyalty liability at the
 * points' value; redeeming books Dr Loyalty liability / Cr Receivables. Every earning is keyed (earn:invoice:ID), so
 * posting twice never gives points twice.
 */
class LoyaltyService
{
    public function __construct(protected PostingService $posting) {}

    public static function enabled(): bool
    {
        return (bool) tenant()->company()?->moduleEnabled('loyalty') && setting('loyalty.enabled', true);
    }

    public static function pointValue(): string
    {
        return (string) setting('loyalty.point_value', '0.010');
    }

    /** @return array<int, array{code: string, min: float, multiplier: float}> lowest first */
    public static function tiers(): array
    {
        return [
            ['code' => 'bronze', 'min' => 0, 'multiplier' => 1.0],
            ['code' => 'silver', 'min' => (float) setting('loyalty.silver_min', 300), 'multiplier' => (float) setting('loyalty.silver_multiplier', 1.25)],
            ['code' => 'gold', 'min' => (float) setting('loyalty.gold_min', 1000), 'multiplier' => (float) setting('loyalty.gold_multiplier', 1.5)],
        ];
    }

    /** Tier from the spend of the last 12 months (before the invoice being rewarded, when given). */
    public function tierFor(Customer $c, ?int $exceptInvoiceId = null): array
    {
        $spend = (float) Invoice::query()->where('customer_id', $c->id)->whereIn('status', ['posted', 'partially_paid', 'paid'])
            ->when($exceptInvoiceId, fn ($q) => $q->whereKeyNot($exceptInvoiceId))
            ->where('issue_date', '>=', now()->subYear()->toDateString())->sum('subtotal');
        $tier = self::tiers()[0];
        foreach (self::tiers() as $t) {
            if ($spend >= $t['min']) {
                $tier = $t;
            }
        }

        return $tier + ['spend' => $spend];
    }

    protected function record(Customer $c, string $type, string $points, array $extra = []): ?LoyaltyTransaction
    {
        if (! empty($extra['reference']) && LoyaltyTransaction::query()->where('reference', $extra['reference'])->exists()) {
            return null; // already done
        }
        $value = Money::round(Money::mul(ltrim((string) $points, '-'), self::pointValue()));
        $tx = LoyaltyTransaction::create($extra + ['customer_id' => $c->id, 'type' => $type, 'points' => $points, 'value' => $value, 'created_by' => auth()->id()]);
        Customer::query()->whereKey($c->id)->update(['loyalty_points' => DB::raw('loyalty_points + ('.((float) $points).')')]);

        return $tx;
    }

    protected function book(string $description, string $value, bool $grant, $source): void
    {
        if (! Money::isPositive($value)) {
            return;
        }
        $this->posting->post(['date' => now()->toDateString(), 'description' => $description, 'source' => $source, 'purpose' => 'loyalty:'.now()->format('YmdHisv').':'.random_int(100, 999),
            'lines' => $grant ? [['account' => 'loyalty_expense', 'debit' => $value], ['account' => 'loyalty_liability', 'credit' => $value]]
                : [['account' => 'loyalty_liability', 'debit' => $value], ['account' => 'loyalty_expense', 'credit' => $value]]]);
    }

    /** Called when an invoice is posted. */
    public function earn(Invoice $invoice): void
    {
        if (! self::enabled() || ! $invoice->customer_id) {
            return;
        }
        $customer = $invoice->customer;
        $base = Money::sub($invoice->subtotal, $invoice->discount_total);
        $tier = $this->tierFor($customer, $invoice->id);
        $points = (string) floor((float) $base * (float) setting('loyalty.points_per_omr', 1) * $tier['multiplier']);
        if ((float) $points > 0 && ($tx = $this->record($customer, 'earn', $points, ['invoice_id' => $invoice->id, 'reference' => 'earn:invoice:'.$invoice->id,
            'notes' => $invoice->displayNumber()]))) {
            $this->book(__('Loyalty points earned').' '.$invoice->displayNumber(), $tx->value, true, $invoice);
        }
        if ($customer->loyalty_tier !== $tier['code']) {
            $customer->forceFill(['loyalty_tier' => $tier['code']])->save();
        }
        // referral bonus: the first posted invoice of a referred customer rewards the referrer once
        if ($customer->referred_by_id && ($referrer = Customer::query()->find($customer->referred_by_id))
            && Invoice::query()->where('customer_id', $customer->id)->whereIn('status', ['posted', 'partially_paid', 'paid'])->count() === 1) {
            $bonus = (string) (int) setting('loyalty.referral_points', 200);
            if ((float) $bonus > 0 && ($tx = $this->record($referrer, 'referral', $bonus, ['invoice_id' => $invoice->id, 'reference' => 'referral:'.$customer->id,
                'notes' => __('Referral: :c', ['c' => $customer->name])]))) {
                $this->book(__('Referral bonus').' '.$customer->name, $tx->value, true, $invoice);
            }
        }
    }

    /** A credit note takes back the points of the returned share (never below zero). */
    public function reverseForCreditNote(CreditNote $cn): void
    {
        if (! self::enabled()) {
            return;
        }
        $earned = (float) LoyaltyTransaction::query()->where('reference', 'earn:invoice:'.$cn->invoice_id)->value('points');
        $invoice = $cn->invoice;
        if ($earned <= 0 || ! $invoice || (float) $invoice->total <= 0) {
            return;
        }
        $customer = $invoice->customer;
        $points = min(floor($earned * (float) $cn->total / (float) $invoice->total), max(0, (float) $customer->fresh()->loyalty_points));
        if ($points > 0 && ($tx = $this->record($customer, 'reverse', (string) -$points, ['invoice_id' => $invoice->id, 'credit_note_id' => $cn->id,
            'reference' => 'reverse:cn:'.$cn->id, 'notes' => $cn->displayNumber()]))) {
            $this->book(__('Loyalty points reversed').' '.$cn->displayNumber(), $tx->value, false, $cn);
        }
    }

    /** Pay (part of) an invoice with points. */
    public function redeem(Invoice $invoice, int $points): Payment
    {
        if (! self::enabled()) {
            throw new BusinessException(__('The loyalty program is not enabled.'));
        }
        $min = (int) setting('loyalty.min_redeem', 100);
        if ($points < $min) {
            throw new BusinessException(__('At least :n points must be redeemed.', ['n' => $min]));
        }

        return DB::transaction(function () use ($invoice, $points) {
            $customer = Customer::query()->whereKey($invoice->customer_id)->lockForUpdate()->firstOrFail();
            if ($points > (float) $customer->loyalty_points) {
                throw new BusinessException(__('The customer has only :n points.', ['n' => (int) $customer->loyalty_points]));
            }
            $value = Money::round(Money::mul($points, self::pointValue()));
            if (Money::cmp($value, $invoice->balanceDue()) > 0) {
                throw new BusinessException(__('The points are worth more than the balance due (:b).', ['b' => money($invoice->balanceDue())]));
            }
            $payment = app(PaymentService::class)->settleFromLiability($invoice, $value, 'loyalty', 'loyalty_liability', __(':n points', ['n' => $points]));
            $this->record($customer, 'redeem', (string) -$points, ['invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'reference' => 'redeem:payment:'.$payment->id,
                'notes' => $invoice->displayNumber()]);
            AuditLogger::log('loyalty.redeemed', $invoice, ['points' => $points, 'value' => $value]);

            return $payment;
        });
    }

    /** The redeeming payment was cancelled: the points come back (the journal is reversed by the payment service). */
    public function refundPayment(Payment $payment): void
    {
        $tx = LoyaltyTransaction::query()->where('reference', 'redeem:payment:'.$payment->id)->first();
        if ($tx) {
            $this->record($tx->customer, 'refund', (string) abs((float) $tx->points), ['invoice_id' => $tx->invoice_id, 'payment_id' => $payment->id,
                'reference' => 'refund:payment:'.$payment->id, 'notes' => $payment->number]);
        }
    }

    /** Manual correction by a manager. */
    public function adjust(Customer $c, int $points, string $notes): void
    {
        DB::transaction(function () use ($c, $points, $notes) {
            if ($points < 0 && abs($points) > (float) $c->fresh()->loyalty_points) {
                throw new BusinessException(__('The customer has only :n points.', ['n' => (int) $c->fresh()->loyalty_points]));
            }
            $tx = $this->record($c, 'adjust', (string) $points, ['notes' => $notes]);
            $this->book(__('Loyalty points adjustment').' '.$c->name, $tx->value, $points > 0, $c);
        });
    }

    /** Points of customers without any activity for N months expire (liability released). */
    public function expire(): int
    {
        $months = (int) setting('loyalty.expiry_months', 12);
        if ($months <= 0 || ! self::enabled()) {
            return 0;
        }
        $n = 0;
        foreach (Customer::query()->where('loyalty_points', '>', 0)->get() as $c) {
            $last = LoyaltyTransaction::query()->where('customer_id', $c->id)->whereIn('type', ['earn', 'redeem', 'referral', 'adjust'])->max('created_at');
            if ($last && now()->parse($last)->lt(now()->subMonths($months))) {
                DB::transaction(function () use ($c) {
                    $tx = $this->record($c, 'expire', (string) -((float) $c->loyalty_points), ['notes' => __('Points expired')]);
                    $this->book(__('Loyalty points expired').' '.$c->name, $tx->value, false, $c);
                });
                $n++;
            }
        }

        return $n;
    }

    public static function referralCode(Customer $c): string
    {
        if (! $c->referral_code) {
            do {
                $code = 'R'.strtoupper(substr(base_convert((string) random_int(100000000, 999999999), 10, 36), 0, 6));
            } while (Customer::query()->where('referral_code', $code)->exists());
            $c->forceFill(['referral_code' => $code])->save();
        }

        return $c->referral_code;
    }
}
