{{-- Loyalty points, referral code and memberships on the customer file --}}
@php
    $co = tenant()->company();
    $hasLoyalty = $co?->moduleEnabled('loyalty');
    $hasMembership = $co?->moduleEnabled('memberships');
@endphp
@if(($hasLoyalty || $hasMembership) && ! $c->isWalkIn())
    @php
        $memberships = $hasMembership ? $c->memberships()->with('plan')->latest('id')->limit(5)->get() : collect();
        $plans = $hasMembership ? \App\Modules\Loyalty\Models\MembershipPlan::query()->where('is_active', true)->orderBy('price')->get() : collect();
        $tier = $hasLoyalty ? app(\App\Modules\Loyalty\Services\LoyaltyService::class)->tierFor($c) : null;
        $pv = (float) \App\Modules\Loyalty\Services\LoyaltyService::pointValue();
    @endphp
    <div class="card mb-3 border-warning-subtle">
        <div class="card-header fw-semibold"><i class="bi bi-stars text-warning me-1"></i>{{ __('Loyalty & membership') }}</div>
        <div class="card-body small">
            @if($hasLoyalty)
                <div class="d-flex align-items-center mb-2">
                    <div><div class="fs-4 fw-bold">{{ number_format((float) $c->loyalty_points) }} <span class="fs-6 fw-normal text-body-secondary">{{ __('points') }}</span></div>
                        <div class="text-body-secondary">{{ __('Worth :v', ['v' => money((float) $c->loyalty_points * $pv)]) }}</div></div>
                    <span class="badge ms-auto fs-6" style="background:{{ ['bronze' => '#b45309', 'silver' => '#64748b', 'gold' => '#ca8a04'][$tier['code']] ?? '#6b7280' }}">{{ status_label($tier['code']) }}</span>
                </div>
                <div class="mb-2">{{ __('Referral code') }}: <b class="font-monospace">{{ \App\Modules\Loyalty\Services\LoyaltyService::referralCode($c) }}</b>
                    @if($c->referrer)<div class="text-body-secondary">{{ __('Referred by') }}: <a href="{{ route('customers.show', $c->referrer) }}">{{ $c->referrer->name }}</a></div>@endif</div>
                @can('loyalty.update')
                    <form method="post" action="{{ route('loyalty.adjust', $c) }}" class="input-group input-group-sm mb-2">@csrf
                        <input type="number" name="points" class="form-control" placeholder="±" required aria-label="{{ __('Points') }}" style="max-width:90px">
                        <input name="notes" class="form-control" placeholder="{{ __('Reason') }}" required aria-label="{{ __('Reason') }}">
                        <button class="btn btn-outline-secondary">{{ __('Adjust') }}</button>
                    </form>
                @endcan
            @endif
            @if($hasMembership)
                @if($hasLoyalty)<hr class="my-2">@endif
                @forelse($memberships as $m)
                    <div class="d-flex align-items-center gap-2 mb-1"><a href="{{ route('memberships.show', $m) }}" class="fw-semibold">{{ $m->plan?->displayName() }}</a>
                        <span class="text-body-secondary">{{ $m->ends_at?->format('Y-m-d') }}</span><span class="ms-auto">{!! status_badge($m->status) !!}</span></div>
                @empty
                    <div class="text-body-secondary mb-1">{{ __('No membership.') }}</div>
                @endforelse
                @can('memberships.create')
                    @if($plans->isNotEmpty())
                        <form method="post" action="{{ route('memberships.sell', $c) }}" class="row g-1 mt-1">@csrf
                            <div class="col-12"><select name="membership_plan_id" class="form-select form-select-sm" aria-label="{{ __('Plan') }}">@foreach($plans as $p)<option value="{{ $p->id }}">{{ $p->displayName() }} — {{ money($p->price) }}</option>@endforeach</select></div>
                            <div class="col-8"><select name="vehicle_id" class="form-select form-select-sm" aria-label="{{ __('Vehicle') }}"><option value="">{{ __('All vehicles') }}</option>@foreach($c->vehicles as $v)<option value="{{ $v->id }}">{{ $v->plate() }}</option>@endforeach</select></div>
                            <div class="col-4 d-grid"><button class="btn btn-sm btn-warning"><i class="bi bi-person-vcard"></i> {{ __('Sell') }}</button></div>
                        </form>
                    @endif
                @endcan
            @endif
        </div>
    </div>
@endif
