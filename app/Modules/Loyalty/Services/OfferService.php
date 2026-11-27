<?php

namespace App\Modules\Loyalty\Services;

use App\Exceptions\BusinessException;
use App\Modules\Core\Models\NotificationTemplate;
use App\Modules\Core\Services\NotificationService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Store\Models\Coupon;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use App\Support\Totals;
use Illuminate\Support\Facades\DB;

/**
 * Discount coupons on invoices and personal offers: a single-use coupon for one customer (e.g. customers who have
 * not visited for months), sent by SMS / WhatsApp / e-mail.
 */
class OfferService
{
    /** Customers whose last visit (job card or invoice) is older than $days. */
    public function inactive(int $days = 180, int $limit = 200)
    {
        $cut = now()->subDays($days)->toDateString();
        $last = DB::table('invoices')->where('company_id', tenant()->id())->whereIn('status', ['posted', 'partially_paid', 'paid'])
            ->groupBy('customer_id')->selectRaw('customer_id, MAX(issue_date) last_visit, COUNT(*) visits, SUM(total) spent');

        return Customer::query()->joinSub($last, 'v', 'v.customer_id', '=', 'customers.id')->where('customers.is_active', true)
            ->where('customers.code', '!=', 'WALKIN')->where('customers.id', '!=', (int) setting('pos.walk_in_customer_id'))
            ->where('v.last_visit', '<', $cut)->orderByDesc('v.spent')->limit($limit)
            ->get(['customers.*', 'v.last_visit', 'v.visits', 'v.spent']);
    }

    public function sendOffer(Customer $c, string $type, mixed $value, int $validDays, string $campaign = 'win-back'): Coupon
    {
        if (! in_array($type, ['percent', 'fixed'], true) || ! Money::isPositive($value)) {
            throw new BusinessException(__('Invalid offer.'));
        }
        do {
            $code = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $c->name) ?: 'OFF', 0, 3)).random_int(1000, 9999);
        } while (Coupon::query()->where('code', $code)->exists());
        $coupon = Coupon::create(['code' => $code, 'type' => $type, 'value' => $value, 'min_order' => 0, 'max_uses' => 1, 'used_count' => 0,
            'starts_at' => today()->toDateString(), 'ends_at' => today()->addDays($validDays)->toDateString(), 'is_active' => true,
            'customer_id' => $c->id, 'campaign' => $campaign]);
        foreach (['mail', 'sms', 'whatsapp', 'database'] as $channel) {
            NotificationTemplate::query()->firstOrCreate(['event' => 'loyalty.offer', 'channel' => $channel], [
                'subject_ar' => 'عرض خاص لك', 'subject_en' => 'A special offer for you',
                'body_ar' => 'عزيزي {{customer_name}}، اشتقنا لك! استخدم الرمز {{code}} لخصم {{offer}} على زيارتك القادمة في {{company_name}} حتى {{until}}.',
                'body_en' => 'Dear {{customer_name}}, we miss you! Use code {{code}} for {{offer}} off your next visit at {{company_name}} until {{until}}.',
            ]);
        }
        app(NotificationService::class)->notifyCustomer($c, 'loyalty.offer', ['code' => $code, 'until' => $coupon->ends_at?->format('Y-m-d'),
            'offer' => $type === 'percent' ? rtrim(rtrim((string) $value, '0'), '.').'%' : money($value)], [$c->preferred_channel ?: 'sms'], $coupon);
        AuditLogger::log('loyalty.offer_sent', $c, ['code' => $code, 'campaign' => $campaign]);

        return $coupon;
    }

    /** Apply a coupon to a draft invoice: the discount is spread over the lines by their amount. */
    public function applyCoupon(Invoice $invoice, string $code): string
    {
        if (! in_array($invoice->status, ['draft', 'held'], true)) {
            throw new BusinessException(__('Only draft invoices can be edited.'));
        }
        $coupon = Coupon::query()->where('code', strtoupper(trim($code)))->where('is_active', true)->first();
        if (! $coupon || ($coupon->starts_at && $coupon->starts_at->gt(today())) || ($coupon->ends_at && $coupon->ends_at->lt(today()))
            || ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) || ($coupon->customer_id && $coupon->customer_id !== $invoice->customer_id)) {
            throw new BusinessException(__('The coupon is not valid for this invoice.'));
        }
        if ($invoice->coupon_id) {
            throw new BusinessException(__('A coupon is already applied to this invoice.'));
        }
        $items = $invoice->items()->whereNotIn('item_type', ['core'])->get();
        $base = Money::sum($items->map(fn ($i) => Money::sub(Money::mul($i->qty, $i->unit_price), $i->discount)));
        if (Money::cmp($base, $coupon->min_order) < 0 || ! Money::isPositive($base)) {
            throw new BusinessException(__('The invoice is below the minimum amount of this coupon (:m).', ['m' => money($coupon->min_order)]));
        }
        $total = $coupon->type === 'percent' ? Money::percent($base, $coupon->value) : Money::min($coupon->value, $base);

        return DB::transaction(function () use ($invoice, $items, $base, $total, $coupon) {
            $given = '0';
            foreach ($items->values() as $n => $i) {
                $lineNet = Money::sub(Money::mul($i->qty, $i->unit_price), $i->discount);
                $share = $n === $items->count() - 1 ? Money::sub($total, $given) : Money::round(Money::div(Money::mul($total, $lineNet), $base, 6));
                $given = Money::add($given, $share);
                $c = Totals::line($i->qty, $i->unit_price, Money::add($i->discount, $share), $i->tax_rate);
                $i->update(['discount' => $c['discount'], 'tax_amount' => $c['tax'], 'line_total' => $c['net']]);
            }
            $invoice->forceFill(['coupon_id' => $coupon->id])->save();
            app(InvoiceService::class)->recalc($invoice);

            return $total;
        });
    }

    /** Called when an invoice is posted: the coupon use is counted (and checked again). */
    public function consumeCoupon(Invoice $invoice): void
    {
        if (! $invoice->coupon_id) {
            return;
        }
        $coupon = Coupon::query()->whereKey($invoice->coupon_id)->lockForUpdate()->first();
        if ($coupon && $coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
            throw new BusinessException(__('The coupon :c has already been used.', ['c' => $coupon->code]));
        }
        $coupon?->increment('used_count');
    }
}
