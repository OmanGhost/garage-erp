@extends('layouts.app')
@section('title', __('Loyalty & rewards'))
@section('content')
@php
    $pv = (float) \App\Modules\Loyalty\Services\LoyaltyService::pointValue();
    $tierColor = ['bronze' => '#b45309', 'silver' => '#64748b', 'gold' => '#ca8a04'];
@endphp
<x-page-header :title="__('Loyalty & rewards')" icon="bi-stars" :breadcrumbs="[__('Customers & Vehicles'), __('Loyalty & rewards')]">
    <a href="{{ route('crud.index', 'loyalty-transactions') }}" class="btn btn-outline-secondary"><i class="bi bi-list-ul"></i> {{ __('Points ledger') }}</a>
    @if(tenant()->company()?->moduleEnabled('memberships'))<a href="{{ route('crud.index', 'memberships') }}" class="btn btn-outline-secondary"><i class="bi bi-person-vcard"></i> {{ __('Memberships') }}</a>@endif
</x-page-header>

<div class="row g-2 mb-3">
    @foreach([
        [__('Points outstanding'), number_format($stats['points']), __('Worth :v', ['v' => money($stats['points'] * $pv)]), 'bi-stars', 'warning'],
        [__('Customers with points'), number_format($stats['members']), __('Active memberships: :n', ['n' => $stats['memberships']]), 'bi-people', 'primary'],
        [__('Earned this month'), number_format($stats['earned']), __('Redeemed: :n', ['n' => number_format($stats['redeemed'])]), 'bi-graph-up-arrow', 'success'],
        [__('Gift card balances'), money($stats['gift_balance']), __('Owed to card holders'), 'bi-gift', 'info'],
    ] as [$l, $v, $sub, $icon, $c])
        <div class="col-6 col-lg-3"><div class="card kpi h-100"><div class="card-body d-flex align-items-center gap-3">
            <span class="kpi-icon bg-{{ $c }}-subtle text-{{ $c }}"><i class="bi {{ $icon }}"></i></span>
            <div class="min-w-0"><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-5">{{ $v }}</div><div class="small text-body-secondary">{{ $sub }}</div></div>
        </div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-xl-7">
        {{-- win-back --}}
        <form method="post" action="{{ route('loyalty.offers') }}" class="card mb-3" x-data="{ all: false }">@csrf
            <div class="card-header d-flex flex-wrap align-items-center gap-2"><i class="bi bi-arrow-counterclockwise"></i><span class="fw-semibold">{{ __('Customers who have not come back') }}</span>
                <div class="btn-group btn-group-sm ms-auto">@foreach([90, 180, 365] as $d)<a class="btn btn-outline-secondary {{ $days === $d ? 'active' : '' }}" href="{{ route('loyalty.dashboard', ['days' => $d]) }}">{{ __(':n days', ['n' => $d]) }}</a>@endforeach</div></div>
            <div class="table-responsive" style="max-height:420px"><table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light sticky-top"><tr><th style="width:2rem"><input type="checkbox" class="form-check-input" x-model="all" @change="$root.querySelectorAll('.wb').forEach(c => c.checked = all)" aria-label="{{ __('Select all') }}"></th>
                    <th>{{ __('Customer') }}</th><th>{{ __('Last visit') }}</th><th class="text-end">{{ __('Visits') }}</th><th class="text-end">{{ __('Total spent') }}</th></tr></thead>
                <tbody>@forelse($inactive as $c)
                    <tr><td><input type="checkbox" class="form-check-input wb" name="customers[]" value="{{ $c->id }}" aria-label="{{ $c->name }}"></td>
                        <td><a href="{{ route('customers.show', $c) }}">{{ $c->name }}</a><div class="small text-body-secondary"><bdi dir="ltr">{{ $c->phone }}</bdi></div></td>
                        <td class="small">{{ $c->last_visit }}</td><td class="text-end">{{ $c->visits }}</td><td class="text-end font-monospace">{{ money($c->spent, false) }}</td></tr>
                @empty<tr><td colspan="5"><div class="empty-state py-3"><i class="bi bi-emoji-smile"></i>{{ __('Every customer visited recently.') }}</div></td></tr>@endforelse</tbody>
            </table></div>
            @can('loyalty.create')
            <div class="card-footer row g-2 align-items-end">
                <div class="col-sm-3"><label class="form-label small mb-0" for="o_t">{{ __('Offer') }}</label><select id="o_t" name="type" class="form-select form-select-sm"><option value="percent">{{ __('Percent off') }}</option><option value="fixed">{{ __('Amount off') }}</option></select></div>
                <div class="col-sm-3"><label class="form-label small mb-0" for="o_v">{{ __('Value') }}</label><input id="o_v" type="number" step="0.001" min="0.001" name="value" value="10" class="form-control form-control-sm" required></div>
                <div class="col-sm-3"><label class="form-label small mb-0" for="o_d">{{ __('Valid (days)') }}</label><input id="o_d" type="number" min="1" max="365" name="valid_days" value="30" class="form-control form-control-sm" required></div>
                <div class="col-sm-3 d-grid"><button class="btn btn-sm btn-primary"><i class="bi bi-send"></i> {{ __('Send personal offer') }}</button></div>
                <div class="col-12 small text-body-secondary">{{ __('Each selected customer receives a single-use coupon code by SMS / WhatsApp / e-mail.') }}</div>
            </div>
            @endcan
        </form>

        {{-- gift cards --}}
        <div class="card mb-3" id="gift-cards">
            <div class="card-header d-flex align-items-center"><i class="bi bi-gift me-1"></i><span class="fw-semibold">{{ __('Gift cards') }}</span><a href="{{ route('crud.index', 'gift-cards') }}" class="ms-auto small">{{ __('All') }}</a></div>
            @can('gift_cards.create')
            <form method="post" action="{{ route('gift-cards.store') }}" class="card-body border-bottom row g-2 align-items-end">@csrf
                <div class="col-sm-3"><label class="form-label small mb-0" for="g_a">{{ __('Amount') }}</label><input id="g_a" type="number" step="0.001" min="0.001" name="amount" class="form-control form-control-sm" required></div>
                <div class="col-sm-4"><label class="form-label small mb-0" for="g_b">{{ __('Paid to') }}</label><select id="g_b" name="bank_account_id" class="form-select form-select-sm" required>@foreach($banks as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
                <div class="col-sm-5"><label class="form-label small mb-0">{{ __('Bought by (optional)') }}</label><x-lookup name="customer_id" type="customers" size="sm"/></div>
                <div class="col-sm-5"><input name="recipient_name" class="form-control form-control-sm" placeholder="{{ __('Recipient name') }}" aria-label="{{ __('Recipient name') }}"></div>
                <div class="col-sm-4"><input name="recipient_phone" class="form-control form-control-sm" dir="ltr" placeholder="{{ __('Recipient phone') }}" aria-label="{{ __('Recipient phone') }}"></div>
                <div class="col-sm-3 d-grid"><button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Issue') }}</button></div>
            </form>
            @endcan
            <ul class="list-group list-group-flush small">
                @forelse($cards as $g)
                    <li class="list-group-item d-flex align-items-center gap-2"><span class="font-monospace fw-semibold">{{ $g->code }}</span><span class="text-body-secondary text-truncate">{{ $g->recipient_name ?? $g->customer?->name }}</span>
                        <span class="ms-auto font-monospace">{{ money($g->balance, false) }} / {{ money($g->initial_amount, false) }}</span>{!! status_badge($g->status) !!}
                        <a href="{{ route('gift-cards.print', $g) }}" target="_blank" rel="noopener" aria-label="{{ __('Print') }}"><i class="bi bi-printer"></i></a></li>
                @empty<li class="list-group-item text-body-secondary">{{ __('No gift cards yet.') }}</li>@endforelse
            </ul>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-3"><div class="card-header fw-semibold"><i class="bi bi-trophy me-1"></i>{{ __('Top point holders') }}</div><ul class="list-group list-group-flush small">
            @forelse($top as $c)<li class="list-group-item d-flex align-items-center gap-2"><a href="{{ route('customers.show', $c) }}">{{ $c->name }}</a>
                @if($c->loyalty_tier)<span class="badge" style="background:{{ $tierColor[$c->loyalty_tier] ?? '#6b7280' }}">{{ status_label($c->loyalty_tier) }}</span>@endif
                <span class="ms-auto font-monospace">{{ number_format((float) $c->loyalty_points) }}</span></li>
            @empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul></div>
        <div class="card mb-3"><div class="card-header fw-semibold"><i class="bi bi-people me-1"></i>{{ __('Best referrers') }}</div><ul class="list-group list-group-flush small">
            @forelse($referrers as $c)<li class="list-group-item d-flex"><a href="{{ route('customers.show', $c) }}">{{ $c->name }}</a><span class="ms-auto badge text-bg-success">{{ trans_choice('{1} 1 referral|[2,*] :n referrals', $c->referrals_count, ['n' => $c->referrals_count]) }}</span></li>
            @empty<li class="list-group-item text-body-secondary">{{ __('Set “Referred by” on a new customer to reward the customer who sent them.') }}</li>@endforelse
        </ul></div>
        @can('settings.update')
        <form method="post" action="{{ route('loyalty.settings') }}" class="card">@csrf
            <div class="card-header fw-semibold"><i class="bi bi-sliders me-1"></i>{{ __('Program settings') }}</div>
            <div class="card-body row g-2 small">
                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="l_on" @checked(setting('loyalty.enabled', true))><label class="form-check-label" for="l_on">{{ __('Customers earn points on posted invoices') }}</label></div></div>
                <div class="col-6"><label class="form-label mb-0" for="l_ppo">{{ __('Points per 1 OMR (before VAT)') }}</label><input id="l_ppo" name="points_per_omr" type="number" step="0.01" min="0" value="{{ setting('loyalty.points_per_omr', 1) }}" class="form-control form-control-sm" required></div>
                <div class="col-6"><label class="form-label mb-0" for="l_pv">{{ __('Value of 1 point (OMR)') }}</label><input id="l_pv" name="point_value" type="number" step="0.001" min="0.001" value="{{ setting('loyalty.point_value', '0.010') }}" class="form-control form-control-sm" required></div>
                <div class="col-6"><label class="form-label mb-0" for="l_min">{{ __('Minimum points to redeem') }}</label><input id="l_min" name="min_redeem" type="number" min="1" value="{{ setting('loyalty.min_redeem', 100) }}" class="form-control form-control-sm" required></div>
                <div class="col-6"><label class="form-label mb-0" for="l_exp">{{ __('Points expire after inactivity (months, 0 = never)') }}</label><input id="l_exp" name="expiry_months" type="number" min="0" value="{{ setting('loyalty.expiry_months', 12) }}" class="form-control form-control-sm" required></div>
                <div class="col-6"><label class="form-label mb-0" for="l_ref">{{ __('Referral bonus (points)') }}</label><input id="l_ref" name="referral_points" type="number" min="0" value="{{ setting('loyalty.referral_points', 200) }}" class="form-control form-control-sm" required></div>
                <div class="col-12 mt-2 fw-semibold">{{ __('Tiers (spend in the last 12 months)') }}</div>
                <div class="col-6"><label class="form-label mb-0" for="l_sm">{{ __('Silver from (OMR)') }}</label><input id="l_sm" name="silver_min" type="number" min="0" value="{{ setting('loyalty.silver_min', 300) }}" class="form-control form-control-sm" required></div>
                <div class="col-6"><label class="form-label mb-0" for="l_sx">{{ __('Silver points ×') }}</label><input id="l_sx" name="silver_multiplier" type="number" step="0.05" min="1" value="{{ setting('loyalty.silver_multiplier', 1.25) }}" class="form-control form-control-sm" required></div>
                <div class="col-6"><label class="form-label mb-0" for="l_gm">{{ __('Gold from (OMR)') }}</label><input id="l_gm" name="gold_min" type="number" min="0" value="{{ setting('loyalty.gold_min', 1000) }}" class="form-control form-control-sm" required></div>
                <div class="col-6"><label class="form-label mb-0" for="l_gx">{{ __('Gold points ×') }}</label><input id="l_gx" name="gold_multiplier" type="number" step="0.05" min="1" value="{{ setting('loyalty.gold_multiplier', 1.5) }}" class="form-control form-control-sm" required></div>
                <div class="col-12"><button class="btn btn-sm btn-primary">{{ __('Save') }}</button></div>
            </div>
        </form>
        @endcan
    </div>
</div>
@endsection
