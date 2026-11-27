<?php

namespace App\Modules\Loyalty\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Customer;
use App\Modules\Loyalty\Models\GiftCard;
use App\Modules\Loyalty\Models\LoyaltyTransaction;
use App\Modules\Loyalty\Models\Membership;
use App\Modules\Loyalty\Models\MembershipPlan;
use App\Modules\Loyalty\Services\GiftCardService;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Modules\Loyalty\Services\MembershipService;
use App\Modules\Loyalty\Services\OfferService;
use App\Modules\Sales\Models\Invoice;
use App\Support\Options;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Memberships, loyalty points, gift cards, coupons and personal offers. */
class LoyaltyController extends Controller
{
    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    protected function module(string $m): void
    {
        abort_unless(tenant()->company()?->moduleEnabled($m), 403, __('The :m module is not enabled in your subscription plan.', ['m' => __(config("erp.modules.$m.label", $m))]));
    }

    /* ---------------- loyalty dashboard ---------------- */

    public function dashboard(Request $request, OfferService $offers)
    {
        $this->can('loyalty.view');
        $days = in_array((int) $request->query('days'), [90, 180, 365], true) ? (int) $request->query('days') : 180;
        $month = now()->startOfMonth();

        return view('loyalty::dashboard', [
            'days' => $days,
            'stats' => [
                'points' => (float) Customer::query()->sum('loyalty_points'),
                'members' => Customer::query()->where('loyalty_points', '>', 0)->count(),
                'earned' => (float) LoyaltyTransaction::query()->whereIn('type', ['earn', 'referral'])->where('created_at', '>=', $month)->sum('points'),
                'redeemed' => (float) -LoyaltyTransaction::query()->where('type', 'redeem')->where('created_at', '>=', $month)->sum('points'),
                'gift_balance' => (float) GiftCard::query()->where('status', 'active')->sum('balance'),
                'memberships' => Membership::query()->where('status', 'active')->count(),
            ],
            'top' => Customer::query()->where('loyalty_points', '>', 0)->orderByDesc('loyalty_points')->limit(10)->get(),
            'inactive' => $offers->inactive($days, 100),
            'cards' => GiftCard::query()->with('customer')->latest('id')->limit(15)->get(),
            'referrers' => Customer::query()->whereIn('id', Customer::query()->whereNotNull('referred_by_id')->select('referred_by_id'))
                ->withCount(['referrals'])->orderByDesc('referrals_count')->limit(8)->get(),
            'banks' => Options::bankAccounts(),
            'tiers' => LoyaltyService::tiers(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $this->can('settings.update');
        $d = $request->validate([
            'points_per_omr' => ['required', 'numeric', 'min:0', 'max:100'], 'point_value' => ['required', 'numeric', 'min:0.001', 'max:10'],
            'min_redeem' => ['required', 'integer', 'min:1'], 'expiry_months' => ['required', 'integer', 'min:0', 'max:120'],
            'referral_points' => ['required', 'integer', 'min:0'], 'silver_min' => ['required', 'numeric', 'min:0'], 'gold_min' => ['required', 'numeric', 'min:0'],
            'silver_multiplier' => ['required', 'numeric', 'min:1', 'max:5'], 'gold_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
        ]);
        Settings::set(collect($d)->mapWithKeys(fn ($v, $k) => ['loyalty.'.$k => (string) $v])->all() + ['loyalty.enabled' => $request->boolean('enabled')]);

        return back()->with('success', __('Saved.'));
    }

    public function adjustPoints(Request $request, Customer $customer, LoyaltyService $loyalty)
    {
        $this->can('loyalty.update');
        $d = $request->validate(['points' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'], 'notes' => ['required', 'string', 'max:190']]);
        $loyalty->adjust($customer, (int) $d['points'], $d['notes']);

        return back()->with('success', __('Points updated.'));
    }

    public function sendOffer(Request $request, OfferService $offers)
    {
        $this->can('loyalty.create');
        $d = $request->validate(['customers' => ['required', 'array', 'max:200'], 'customers.*' => ['integer', Rule::exists('customers', 'id')->where('company_id', tenant()->id())],
            'type' => ['required', 'in:percent,fixed'], 'value' => ['required', 'numeric', 'gt:0', 'max:100000'], 'valid_days' => ['required', 'integer', 'min:1', 'max:365']]);
        $n = 0;
        foreach (Customer::query()->whereIn('id', $d['customers'])->get() as $c) {
            $offers->sendOffer($c, $d['type'], $d['value'], (int) $d['valid_days']);
            $n++;
        }

        return back()->with('success', __(':n personal offers sent.', ['n' => $n]));
    }

    /* ---------------- gift cards ---------------- */

    public function issueGiftCard(Request $request, GiftCardService $cards)
    {
        $this->can('gift_cards.create');
        $d = $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'max:100000'], 'bank_account_id' => ['required', 'integer', Rule::exists('bank_accounts', 'id')->where('company_id', tenant()->id())],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', tenant()->id())], 'recipient_name' => ['nullable', 'string', 'max:190'],
            'recipient_phone' => ['nullable', 'string', 'max:30'], 'expires_at' => ['nullable', 'date', 'after:today']]);
        $card = $cards->issue($d['amount'], (int) $d['bank_account_id'], $d);

        return back()->with('success', __('Gift card :c issued.', ['c' => $card->code]))
            ->with('print_voucher', route('gift-cards.print', $card))->with('print_label', __('Print gift card'));
    }

    public function printGiftCard(GiftCard $card)
    {
        $this->can('gift_cards.view');

        return view('loyalty::gift-card-print', ['card' => $card->load('customer'), 'paper' => 'A5']);
    }

    /* ---------------- memberships ---------------- */

    public function sellMembership(Request $request, Customer $customer, MembershipService $svc)
    {
        $this->module('memberships');
        $this->can('memberships.create');
        $d = $request->validate(['membership_plan_id' => ['required', 'integer', Rule::exists('membership_plans', 'id')->where('company_id', tenant()->id())],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->where('company_id', tenant()->id())->where('customer_id', $customer->id)]]);
        [$m, $inv] = $svc->sell($customer, MembershipPlan::query()->findOrFail($d['membership_plan_id']), $d['vehicle_id'] ?? null);

        return redirect()->route('invoices.show', $inv)->with('success', __('Membership :n created. It becomes active when this invoice is posted.', ['n' => $m->number]));
    }

    public function membership(Membership $membership)
    {
        $this->module('memberships');
        $this->can('memberships.view');

        return view('loyalty::membership', ['m' => $membership->load('plan', 'customer', 'vehicle', 'invoice', 'balances.service', 'usages.invoice', 'usages.service')]);
    }

    public function printMembership(Membership $membership)
    {
        $this->module('memberships');
        $this->can('memberships.view');

        return view('loyalty::membership-card', ['m' => $membership->load('plan.items.service', 'customer', 'vehicle'), 'paper' => 'A5']);
    }

    /* ---------------- invoice actions ---------------- */

    public function invoiceAction(Request $request, Invoice $invoice, string $action)
    {
        switch ($action) {
            case 'membership':
                $this->module('memberships');
                $this->can('invoices.update');
                $v = app(MembershipService::class)->applyToInvoice($invoice);

                return back()->with('success', __('Membership applied: :v discount.', ['v' => money($v)]));
            case 'coupon':
                $this->can('invoices.update');
                $v = app(OfferService::class)->applyCoupon($invoice, (string) $request->validate(['code' => ['required', 'string', 'max:40']])['code']);

                return back()->with('success', __('Coupon applied: :v discount.', ['v' => money($v)]));
            case 'points':
                $this->module('loyalty');
                $this->can('payments.create');
                app(LoyaltyService::class)->redeem($invoice, (int) $request->validate(['points' => ['required', 'integer', 'min:1']])['points']);

                return back()->with('success', __('Points redeemed.'));
            case 'gift-card':
                $this->module('loyalty');
                $this->can('payments.create');
                $d = $request->validate(['code' => ['required', 'string', 'max:30'], 'amount' => ['nullable', 'numeric', 'gt:0']]);
                app(GiftCardService::class)->redeem($invoice, $d['code'], $d['amount'] ?? null);

                return back()->with('success', __('Gift card applied.'));
        }
        abort(404);
    }
}
