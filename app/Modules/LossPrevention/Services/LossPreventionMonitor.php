<?php

namespace App\Modules\LossPrevention\Services;

use App\Models\User;
use App\Modules\Core\Notifications\SystemMessage;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\LossPrevention\Models\FraudAlert;
use App\Modules\Sales\Models\CreditNote;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Models\PosSession;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Services\CapacityService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Internal fraud & loss prevention. Every audit event passes through observe(); the few sensitive ones are checked
 * against the rules once the transaction commits (a rolled-back action raises nothing, and a failing rule never
 * breaks the business operation). Only actions of staff users are watched — scheduled jobs, public links and the
 * customer portal are not. Alerts are aggregated by fingerprint (one alert per document, or per user / part and
 * period); medium and high ones are sent to managers immediately, low ones in the daily digest.
 */
class LossPreventionMonitor
{
    /** Events that are checked by a rule. */
    public const EVENTS = ['invoice.posted', 'payment.cancelled', 'credit_note.posted', 'stock_adjustment.posted', 'job_card.status', 'pos.session_closed', 'deleted', 'updated'];

    /** Sensitive events that raise an alert when done outside working hours. */
    public const AFTER_HOURS = ['invoice.posted', 'payment.received', 'payment.cancelled', 'credit_note.posted', 'stock_adjustment.posted', 'stock_count.posted',
        'pos.session_opened', 'pos.session_closed', 'journal.reversed', 'supplier_invoice.posted', 'goods_receipt.posted', 'gift_card.issued'];

    /** Jobs that are free or billed under a contract elsewhere. */
    public const EXEMPT_JOBS = ['warranty', 'internal', 'insurance', 'fleet'];

    protected static bool $busy = false;

    public static function observe(string $event, ?Model $model, ?array $old, ?array $new): void
    {
        if (self::$busy || ! (in_array($event, self::EVENTS, true) || in_array($event, self::AFTER_HOURS, true))) {
            return;
        }
        if ($event === 'deleted' && ! $model instanceof Invoice) {
            return;
        }
        if ($event === 'updated' && ! $model instanceof Part) {
            return;
        }
        $user = auth()->user();
        $company = tenant()->company();
        if (! $user || $user->customer_id || ! $company || ! $company->moduleEnabled('loss_prevention')) {
            return;
        }
        $at = now();
        $ctx = ['event' => $event, 'model' => $model, 'old' => $old, 'new' => $new, 'user' => $user, 'at' => $at];
        DB::afterCommit(function () use ($ctx) {
            self::$busy = true;
            try {
                app(self::class)->inspect($ctx);
            } catch (\Throwable $e) {
                report($e);
            } finally {
                self::$busy = false;
            }
        });
    }

    protected function setting(string $key, $default = null): string
    {
        return (string) setting('fraud.'.$key, config('erp.defaults')['fraud.'.$key] ?? $default);
    }

    protected function inspect(array $c): void
    {
        $m = $c['model'];
        match ($c['event']) {
            'invoice.posted' => $this->invoicePosted(Invoice::query()->with('items.part', 'jobCard', 'membership.plan')->find($m?->getKey()), $c),
            'payment.cancelled' => $this->paymentCancelled($m, $c),
            'credit_note.posted' => $this->creditNote($m, $c),
            'stock_adjustment.posted' => $this->adjustment(StockAdjustment::query()->with('items.part')->find($m?->getKey()), $c),
            'job_card.status' => ($c['new']['to'] ?? null) === JobCard::DELIVERED ? $this->delivered(JobCard::query()->find($m?->getKey()), $c) : null,
            'pos.session_closed' => $this->sessionClosed(PosSession::query()->find($m?->getKey()), $c),
            'deleted' => $this->invoiceDeleted($c),
            'updated' => $this->partPriceChanged($m, $c),
            default => null,
        };
        if (in_array($c['event'], self::AFTER_HOURS, true) || ($c['event'] === 'job_card.status' && ($c['new']['to'] ?? null) === JobCard::DELIVERED)) {
            $this->afterHours($c);
        }
    }

    /* ---------------------------------------------------------------- rules */

    protected function invoicePosted(?Invoice $inv, array $c): void
    {
        if (! $inv || in_array($inv->type, ['insurance', 'fleet'], true) || in_array($inv->jobCard?->job_type, self::EXEMPT_JOBS, true)) {
            return;                                    // contract pricing (insurance assessments, fleet agreements) and free jobs
        }
        $plan = $inv->membership?->plan;
        $gross = '0';
        $manual = '0';
        $belowCost = '0';
        $belowLines = [];
        $overrides = [];
        foreach ($inv->items as $i) {
            if ($i->item_type === 'core') {
                continue;
            }
            $lineGross = Money::mul($i->qty, $i->unit_price);
            $gross = Money::add($gross, $lineGross);
            $includedByMembership = $i->membership_id || ($plan && $i->service_id && Money::cmp($i->discount, $i->unit_price) >= 0);
            if (! $includedByMembership) {
                $manual = Money::add($manual, $i->discount);
            }
            // sold below the cost booked when the invoice posted
            if ($i->item_type === 'part' && Money::isPositive($i->cost_total) && Money::cmp($i->line_total, $i->cost_total) < 0) {
                $loss = Money::sub($i->cost_total, $i->line_total);
                $belowCost = Money::add($belowCost, $loss);
                $belowLines[] = ['part' => $i->part?->part_number ?? $i->description, 'qty' => (string) $i->qty, 'net' => (string) $i->line_total, 'cost' => (string) $i->cost_total];
            }
            // unit price typed below the catalogue price for the invoice's price level
            if ($i->item_type === 'part' && $i->part && ! $i->job_card_part_id) {
                $list = $inv->price_level ? $i->part->priceFor($inv->price_level)
                    : Money::min(Money::min($i->part->priceFor('retail'), $i->part->priceFor('wholesale')), $i->part->priceFor('workshop'));
                $limit = Money::percent($list, Money::sub('100', $this->setting('price_override_percent', 10)));
                if (Money::isPositive($list) && Money::cmp($i->unit_price, $limit) < 0) {
                    $overrides[] = ['part' => $i->part->part_number, 'list' => $list, 'charged' => (string) $i->unit_price];
                }
            }
        }
        // coupon discount and the plan's own discounts are not manual
        if ($inv->coupon_id && ($coupon = \App\Modules\Store\Models\Coupon::query()->find($inv->coupon_id))) {
            $manual = Money::sub($manual, $coupon->type === 'percent' ? Money::percent($gross, $coupon->value) : Money::min($coupon->value, $gross));
        }
        $customer = $inv->customer;
        $agreed = max((float) ($customer?->discount_percent ?? 0), (float) ($customer?->group?->discount_percent ?? 0),
            $plan ? max((float) $plan->parts_discount, (float) $plan->labor_discount) : 0);   // standing discounts are not manual
        $allowed = (float) $this->setting('max_discount_percent', 15) + $agreed;
        $pct = Money::isPositive($gross) ? round((float) $manual * 100 / (float) $gross, 1) : 0;
        if ($pct > $allowed) {
            $this->raise('high_discount', $pct > 2 * $allowed ? 'high' : 'medium', $inv, __('Discount of :p% on invoice :n', ['p' => $pct, 'n' => $inv->number]),
                $manual, ['percent' => $pct, 'allowed' => $allowed, 'gross' => $gross, 'customer' => $inv->customer?->name], $c);
        }
        if (Money::isPositive($belowCost)) {
            $this->raise('below_cost', Money::cmp($belowCost, $this->setting('large_amount', 100)) >= 0 ? 'high' : 'medium', $inv,
                __('Invoice :n sold parts below cost', ['n' => $inv->number]), $belowCost, ['lines' => $belowLines], $c);
        }
        if ($overrides) {
            $this->raise('price_override', 'medium', $inv, __('Prices below the price list on invoice :n', ['n' => $inv->number]), null, ['lines' => $overrides], $c);
        }
    }

    protected function paymentCancelled(?Model $p, array $c): void
    {
        $p = $p instanceof Payment ? $p->fresh() : null;
        if (! $p) {
            return;
        }
        $severity = $p->method === 'cash' && Money::cmp($p->amount, $this->setting('large_amount', 100)) >= 0 ? 'high' : 'medium';
        $this->raise('payment_cancelled', $severity, $p, __('Receipt :n (:m) cancelled', ['n' => $p->number, 'm' => status_label($p->method)]),
            $p->amount, ['reason' => $c['new']['reason'] ?? null, 'method' => $p->method, 'date' => $p->date?->toDateString()], $c);
    }

    protected function creditNote(?Model $cn, array $c): void
    {
        $cn = $cn instanceof CreditNote ? $cn->fresh('invoice') : null;
        if (! $cn) {
            return;
        }
        $large = Money::cmp($cn->total, $this->setting('large_amount', 100)) >= 0;
        $sameDay = $cn->invoice && $cn->invoice->posted_at && $cn->invoice->posted_at->isSameDay($c['at']);
        $this->raise('credit_note', $large ? 'high' : ($sameDay ? 'medium' : 'low'), $cn, __('Return / credit note :n on invoice :i', ['n' => $cn->number, 'i' => $cn->invoice?->number]),
            $cn->total, ['invoice' => $cn->invoice?->number, 'same_day' => $sameDay, 'reason' => $cn->reason ?? null], $c);
    }

    /** A held / draft POS sale deleted (rung up, then voided). Draft workshop invoices removed with a cancelled job are not checked. */
    protected function invoiceDeleted(array $c): void
    {
        $old = $c['old'] ?? [];
        if (($old['type'] ?? null) !== 'pos' || ! in_array($old['status'] ?? null, ['held', 'draft'], true) || ! Money::isPositive($old['total'] ?? 0)) {
            return;
        }
        $this->raise('void_sale', Money::cmp($old['total'], $this->setting('large_amount', 100)) >= 0 ? 'medium' : 'low', $c['model'],
            __('Held POS sale of :a deleted', ['a' => money($old['total'])]), $old['total'], ['label' => $old['held_label'] ?? null, 'session' => $old['pos_session_id'] ?? null], $c);
    }

    protected function adjustment(?StockAdjustment $adj, array $c): void
    {
        if (! $adj || $adj->reason === 'opening') {
            return;
        }
        $loss = (string) ($c['new']['loss'] ?? '0');
        if (Money::cmp($loss, $this->setting('write_off_min', 20)) >= 0) {
            $this->raise('stock_write_off', Money::cmp($loss, $this->setting('large_amount', 100)) >= 0 ? 'high' : 'medium', $adj,
                __('Stock removed without a job card or invoice (:n)', ['n' => $adj->number]), $loss,
                ['reason' => $adj->reason, 'parts' => $adj->items->filter(fn ($i) => (float) $i->qty < 0)->map(fn ($i) => $i->part?->part_number.' × '.abs((float) $i->qty))->values()->all()], $c);
        }
        // the same user adjusting stock again and again
        $week = $c['at']->copy()->startOfWeek();
        $n = DB::table('audit_logs')->where('company_id', tenant()->id())->where('user_id', $c['user']->id)->where('event', 'stock_adjustment.posted')
            ->where('created_at', '>=', $week)->count();
        if ($n >= (int) $this->setting('adjustments_per_week', 3)) {
            $this->raise('frequent_adjustments', 'medium', null, __(':u posted :n stock adjustments this week', ['u' => $c['user']->name, 'n' => $n]), null,
                ['week' => $week->toDateString(), 'count' => $n], $c, 'frequent_adjustments:'.$c['user']->id.':'.$week->format('o-W'), $n);
        }
        // the same part adjusted repeatedly
        $since = $c['at']->copy()->subDays(30);
        foreach ($adj->items->pluck('part_id')->unique() as $partId) {
            $times = DB::table('stock_adjustment_items as i')->join('stock_adjustments as a', 'a.id', '=', 'i.stock_adjustment_id')
                ->where('a.company_id', tenant()->id())->where('a.status', 'posted')->where('i.part_id', $partId)->where('a.posted_at', '>=', $since)->count();
            if ($times >= (int) $this->setting('part_adjustments_30d', 3)) {
                $part = Part::query()->find($partId);
                $this->raise('part_adjustments', 'medium', $part, __('Part :p adjusted :n times in 30 days', ['p' => $part?->part_number, 'n' => $times]), null,
                    ['count' => $times], $c, 'part_adjustments:'.$partId.':'.$c['at']->format('Y-m'), $times);
            }
        }
    }

    /** Parts issued to a job that are not on any posted invoice when the vehicle leaves. */
    protected function delivered(?JobCard $jc, array $c): void
    {
        if (! $jc || in_array($jc->job_type, self::EXEMPT_JOBS, true) || $jc->fleet_id || $jc->insurance_claim_id) {
            return;
        }
        $billed = DB::table('invoice_items as ii')->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->where('i.job_card_id', $jc->id)->whereIn('i.status', ['posted', 'partially_paid', 'paid'])->whereNotNull('ii.job_card_part_id')
            ->groupBy('ii.job_card_part_id')->selectRaw('ii.job_card_part_id, SUM(ii.qty) q')->pluck('q', 'job_card_part_id');
        $missing = [];
        $value = '0';
        foreach ($jc->parts()->with('part')->get() as $p) {
            $short = Money::sub($p->netQty(), (string) ($billed[$p->id] ?? 0));
            if (Money::isPositive($short)) {
                $missing[] = ['part' => $p->part?->part_number, 'qty' => $short];
                $value = Money::add($value, Money::mul($short, $p->unit_cost));
            }
        }
        if ($missing) {
            $this->raise('unbilled_parts', 'high', $jc, __('Job :n delivered with parts that were not invoiced', ['n' => $jc->number]), $value, ['parts' => $missing], $c);
        }
    }

    protected function sessionClosed(?PosSession $s, array $c): void
    {
        if (! $s) {
            return;
        }
        $diff = (string) $s->difference;
        $min = $this->setting('cash_difference_min', 1);
        if (Money::cmp(Money::neg($diff), $min) >= 0) {
            $repeat = FraudAlert::query()->where('rule', 'cash_shortage')->where('user_id', $s->user_id)->where('created_at', '>=', $c['at']->copy()->subDays(30))->count();
            $this->raise('cash_shortage', $repeat || Money::cmp(Money::neg($diff), $this->setting('large_amount', 100)) >= 0 ? 'high' : 'medium', $s,
                __('Cash shortage of :a in session :n', ['a' => money(Money::neg($diff)), 'n' => $s->number]), Money::neg($diff),
                ['expected' => (string) $s->expected_cash, 'counted' => (string) $s->counted_cash, 'reason' => $s->difference_reason, 'shortages_last_30_days' => $repeat + 1], $c);
        } elseif (Money::cmp($diff, $min) >= 0) {
            $this->raise('cash_overage', 'low', $s, __('Cash overage of :a in session :n', ['a' => money($diff), 'n' => $s->number]), $diff,
                ['expected' => (string) $s->expected_cash, 'counted' => (string) $s->counted_cash, 'reason' => $s->difference_reason], $c);
        }
    }

    protected function partPriceChanged(?Model $part, array $c): void
    {
        $old = $c['old']['sale_price'] ?? null;
        $new = $c['new']['sale_price'] ?? null;
        if ($old === null || $new === null || ! Money::isPositive($old)) {
            return;
        }
        $drop = round((1 - (float) $new / (float) $old) * 100, 1);
        if ($drop >= (float) $this->setting('price_drop_percent', 20)) {
            $this->raise('price_change', 'medium', $part, __('Selling prices cut by :u', ['u' => $c['user']->name]), null,
                ['actions' => [$part->part_number.': '.money($old, false).' → '.money($new, false).' (−'.$drop.'%)']], $c,
                'price_change:'.$c['user']->id.':'.$c['at']->toDateString(), null, true);
        }
    }

    protected function afterHours(array $c): void
    {
        $model = $c['model'];
        $branchId = $model?->getAttribute('branch_id') ?? tenant()->branchId();
        $window = app(CapacityService::class)->window($c['at']->copy(), $branchId);
        $grace = (int) $this->setting('after_hours_grace_minutes', 30);
        if ($window && $c['at']->between($window[0]->copy()->subMinutes($grace), $window[1]->copy()->addMinutes($grace))) {
            return;
        }
        $day = $c['at']->toDateString();
        $this->raise('after_hours', 'low', null, __(':u worked outside working hours on :d', ['u' => $c['user']->name, 'd' => $day]), null,
            ['actions' => [$c['at']->format('H:i').' '.$c['event']]], $c, 'after_hours:'.$c['user']->id.':'.$day, null, true);
    }

    /* ---------------------------------------------------------------- store */

    /**
     * Create the alert, or update the existing one with the same fingerprint (counting occurrences).
     */
    protected function raise(string $rule, string $severity, ?Model $subject, string $title, mixed $amount, array $details, array $c,
        ?string $fingerprint = null, ?int $occurrences = null, bool $appendActions = false): FraudAlert
    {
        $fingerprint ??= $rule.':'.($subject ? $subject->getMorphClass().':'.$subject->getKey() : 'none:'.$c['at']->format('YmdHis'));
        $alert = FraudAlert::query()->where('fingerprint', $fingerprint)->lockForUpdate()->first();
        $rank = ['low' => 1, 'medium' => 2, 'high' => 3];
        if ($alert) {
            $merged = $alert->details ?? [];
            if ($appendActions) {
                $merged['actions'] = array_slice(array_merge($merged['actions'] ?? [], $details['actions'] ?? []), -50);
            } else {
                $merged = array_merge($merged, $details);
            }
            $alert->forceFill([
                'occurrences' => $occurrences ?? $alert->occurrences + 1, 'last_seen_at' => $c['at'], 'title' => $title, 'details' => $merged,
                'severity' => $rank[$severity] > $rank[$alert->severity] ? $severity : $alert->severity,
                'status' => $alert->status === 'substantiated' ? 'substantiated' : 'unreviewed',
            ])->save();

            return $alert;
        }
        $alert = FraudAlert::create([
            'branch_id' => $subject?->getAttribute('branch_id') ?? tenant()->branchId(), 'rule' => $rule, 'severity' => $severity,
            'user_id' => $c['user']->id, 'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(),
            'title' => mb_substr($title, 0, 250), 'amount' => $amount !== null ? Money::round($amount) : null, 'details' => $details,
            'fingerprint' => mb_substr($fingerprint, 0, 120), 'occurrences' => $occurrences ?? 1, 'last_seen_at' => $c['at'], 'status' => 'unreviewed',
        ]);
        if ($severity !== 'low') {
            $this->notify($alert);
        }

        return $alert;
    }

    /** People who review alerts (never the person who triggered it). */
    public static function reviewers(?int $exceptUserId = null)
    {
        return User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->where('is_active', true)
            ->when($exceptUserId, fn ($q) => $q->whereKeyNot($exceptUserId))->get()->filter(fn ($u) => $u->can('fraud_alerts.view'));
    }

    protected function notify(FraudAlert $a): void
    {
        $to = self::reviewers($a->user_id);
        if ($to->isNotEmpty()) {
            Notification::send($to, new SystemMessage(__('Suspicious operation').': '.$a->title, $a->user?->name ?? '', route('loss-prevention.show', $a), 'danger'));
        }
    }
}
