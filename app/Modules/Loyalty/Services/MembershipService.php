<?php

namespace App\Modules\Loyalty\Services;

use App\Exceptions\BusinessException;
use App\Modules\CRM\Models\Customer;
use App\Modules\Loyalty\Models\Membership;
use App\Modules\Loyalty\Models\MembershipPlan;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Services\InvoiceService;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Totals;
use Illuminate\Support\Facades\DB;

/**
 * Maintenance memberships: sold with an invoice (revenue recognised at sale — no deferral), active once that
 * invoice is posted. On a draft invoice "apply membership" gives included services for free (discount = line
 * amount, so VAT stays correct) and the plan's parts / labour discounts. Included services are consumed only when
 * the invoice is posted, with the membership locked and the balance checked again.
 */
class MembershipService
{
    public static function enabled(): bool
    {
        return (bool) tenant()->company()?->moduleEnabled('memberships');
    }

    /** @return array{0: Membership, 1: Invoice} */
    public function sell(Customer $customer, MembershipPlan $plan, ?int $vehicleId = null): array
    {
        if (! $plan->is_active) {
            throw new BusinessException(__('This membership plan is not active.'));
        }

        return DB::transaction(function () use ($customer, $plan, $vehicleId) {
            $m = Membership::create(['number' => DocumentNumber::next('membership'), 'customer_id' => $customer->id, 'vehicle_id' => $vehicleId,
                'membership_plan_id' => $plan->id, 'price' => $plan->price, 'status' => 'pending', 'created_by' => auth()->id()]);
            $inv = app(InvoiceService::class)->createDraft(['customer_id' => $customer->id, 'notes' => __('Membership :n', ['n' => $m->number])], [[
                'item_type' => 'other', 'description' => __('Maintenance membership: :p (:m months)', ['p' => $plan->displayName(), 'm' => $plan->duration_months]),
                'qty' => 1, 'unit_price' => $plan->price, 'tax_rate' => tenant()->company()?->vat_rate ?? config('erp.vat_rate'),
            ]]);
            $m->update(['invoice_id' => $inv->id]);

            return [$m, $inv];
        });
    }

    /** Called when an invoice is posted: memberships sold with it become active. */
    public function activateFromInvoice(Invoice $invoice): void
    {
        foreach (Membership::query()->with('plan.items')->where('invoice_id', $invoice->id)->where('status', 'pending')->get() as $m) {
            $start = today();
            $current = Membership::query()->where('customer_id', $m->customer_id)->where('status', 'active')->whereKeyNot($m->id)
                ->when($m->vehicle_id, fn ($q) => $q->where('vehicle_id', $m->vehicle_id))->max('ends_at');
            if ($current && \Illuminate\Support\Carbon::parse($current)->gte($start)) {
                $start = \Illuminate\Support\Carbon::parse($current)->addDay(); // renewal continues after the current one
            }
            $m->update(['status' => 'active', 'starts_at' => $start, 'ends_at' => $start->copy()->addMonths($m->plan->duration_months)->subDay()]);
            foreach ($m->plan->items as $i) {
                $m->balances()->updateOrCreate(['service_id' => $i->service_id], ['included' => $i->qty, 'used' => 0]);
            }
            AuditLogger::log('membership.activated', $m, ['number' => $m->number]);
        }
    }

    public function activeFor(int $customerId, ?int $vehicleId = null): ?Membership
    {
        return Membership::query()->with('plan', 'balances')->where('customer_id', $customerId)->where('status', 'active')
            ->whereDate('starts_at', '<=', today())->whereDate('ends_at', '>=', today())
            ->where(fn ($q) => $q->whereNull('vehicle_id')->when($vehicleId, fn ($w) => $w->orWhere('vehicle_id', $vehicleId)))
            ->orderBy('ends_at')->first();
    }

    /** Apply the customer's membership to a draft invoice. Returns the discount granted. */
    public function applyToInvoice(Invoice $invoice): string
    {
        if (! in_array($invoice->status, ['draft', 'held'], true)) {
            throw new BusinessException(__('Only draft invoices can be edited.'));
        }
        $m = $this->activeFor($invoice->customer_id, $invoice->jobCard?->vehicle_id);
        if (! $m) {
            throw new BusinessException(__('The customer has no active membership.'));
        }

        return DB::transaction(function () use ($invoice, $m) {
            $left = $m->balances->mapWithKeys(fn ($b) => [$b->service_id => $b->left()])->all();
            $total = '0';
            foreach ($invoice->items()->get() as $item) {
                if (in_array($item->item_type, ['core', 'other'], true) && ! $item->service_id) {
                    continue;
                }
                $gross = Money::mul($item->qty, $item->unit_price);
                $discount = null;
                if ($item->service_id && ($left[$item->service_id] ?? 0) > 0) {
                    $covered = min((float) $item->qty, (float) $left[$item->service_id]);
                    $left[$item->service_id] -= $covered;
                    $discount = Money::min($gross, Money::mul($covered, $item->unit_price));
                    if (Money::cmp($covered, $item->qty) < 0) { // partly covered: the labour discount applies to the rest
                        $discount = Money::add($discount, Money::percent(Money::sub($gross, $discount), $m->plan->labor_discount));
                    }
                } elseif ($item->item_type === 'part' && (float) $m->plan->parts_discount > 0) {
                    $discount = Money::percent($gross, $m->plan->parts_discount);
                } elseif (in_array($item->item_type, ['service', 'labor'], true) && (float) $m->plan->labor_discount > 0) {
                    $discount = Money::percent($gross, $m->plan->labor_discount);
                }
                if ($discount === null) {
                    continue;
                }
                $discount = Money::max($discount, $item->discount); // never lower an existing discount
                $c = Totals::line($item->qty, $item->unit_price, $discount, $item->tax_rate);
                $item->update(['discount' => $c['discount'], 'tax_amount' => $c['tax'], 'line_total' => $c['net'], 'membership_id' => $m->id]);
                $total = Money::add($total, $c['discount']);
            }
            $invoice->forceFill(['membership_id' => $m->id])->save();   // on the header too: line flags are lost when the draft is edited
            app(InvoiceService::class)->recalc($invoice);

            return $total;
        });
    }

    /**
     * Called when an invoice is posted: consume the included services of the membership applied to it. Every line is
     * evaluated (line flags do not survive a draft edit); a line counts as included when it is a covered service whose
     * discount reaches its unit price. The balance is checked again with the membership locked.
     */
    public function consumeOnPost(Invoice $invoice): void
    {
        if (! $invoice->membership_id) {
            return;
        }
        $m = Membership::query()->with('balances.service')->whereKey($invoice->membership_id)->lockForUpdate()->first();
        if (! $m || ! $m->isActive() || (int) $m->customer_id !== (int) $invoice->customer_id) {
            throw new BusinessException(__('The membership applied to this invoice is no longer active. Remove it and apply again.'));
        }
        foreach ($invoice->items()->get() as $item) {
            $balance = $item->service_id ? $m->balances->firstWhere('service_id', $item->service_id) : null;
            $coveredQty = 0;
            if ($balance && Money::isPositive($item->unit_price) && Money::cmp($item->discount, $item->unit_price) >= 0) {
                $coveredQty = (int) min(floor((float) $item->discount / (float) $item->unit_price + 0.0001), (float) $item->qty);
                if ($coveredQty > $balance->left()) {
                    throw new BusinessException(__('The membership has only :n included “:s” left.', ['n' => $balance->left(), 's' => $balance->service?->displayName()]));
                }
                $balance->increment('used', $coveredQty);
                $balance->refresh();
            }
            if ($coveredQty || ($item->membership_id && Money::isPositive($item->discount))) {
                $m->usages()->firstOrCreate(['invoice_item_id' => $item->id], ['invoice_id' => $invoice->id, 'service_id' => $item->service_id,
                    'qty' => $coveredQty ?: $item->qty, 'value' => $item->discount, 'kind' => $coveredQty ? 'included' : 'discount']);
            }
        }
    }

    public function expire(): int
    {
        return Membership::query()->where('status', 'active')->whereDate('ends_at', '<', today())->update(['status' => 'expired']);
    }

    /** Roadside call included in the membership (used by roadside assistance). */
    public function useRoadsideCall(Membership $m): void
    {
        DB::transaction(function () use ($m) {
            $m = Membership::query()->with('plan')->whereKey($m->id)->lockForUpdate()->firstOrFail();
            if (! $m->isActive()) {
                throw new BusinessException(__('The membership is no longer active.'));
            }
            if ($m->roadsideLeft() < 1) {
                throw new BusinessException(__('No roadside calls left in this membership.'));
            }
            $m->increment('roadside_used');
        });
    }
}
