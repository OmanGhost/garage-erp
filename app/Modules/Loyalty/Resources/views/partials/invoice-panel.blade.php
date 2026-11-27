{{-- Membership, coupon, points and gift card on an invoice --}}
@php
    $co = tenant()->company();
    $draft = in_array($inv->status, ['draft', 'held'], true);
    $due = $inv->isPosted() ? $inv->balanceDue() : '0';
    $hasLoyalty = $co?->moduleEnabled('loyalty') && ! $inv->customer?->isWalkIn();
    $member = $draft && $co?->moduleEnabled('memberships') ? app(\App\Modules\Loyalty\Services\MembershipService::class)->activeFor($inv->customer_id, $inv->jobCard?->vehicle_id) : null;
    $points = (float) $inv->customer?->loyalty_points;
    $pv = (float) \App\Modules\Loyalty\Services\LoyaltyService::pointValue();
    $earned = $hasLoyalty ? \App\Modules\Loyalty\Models\LoyaltyTransaction::query()->where('reference', 'earn:invoice:'.$inv->id)->value('points') : null;
@endphp
@if(($draft && ($member || $hasLoyalty)) || ($inv->isPosted() && $hasLoyalty && (\App\Support\Money::isPositive($due) || $earned)))
<div class="card mb-3 border-warning-subtle">
    <div class="card-header fw-semibold"><i class="bi bi-stars text-warning me-1"></i>{{ __('Membership & rewards') }}</div>
    <div class="card-body small">
        @if($draft)
            @if($member)
                <div class="mb-1">{{ __('Active membership') }}: <a href="{{ route('memberships.show', $member) }}" class="fw-semibold">{{ $member->plan?->displayName() }}</a></div>
                @can('invoices.update')<form method="post" action="{{ route('invoices.loyalty', [$inv, 'membership']) }}" class="d-grid mb-2">@csrf<button class="btn btn-sm btn-warning"><i class="bi bi-person-vcard"></i> {{ __('Apply membership benefits') }}</button></form>@endcan
            @endif
            @if($hasLoyalty && ! $inv->coupon_id)
                @can('invoices.update')
                    <form method="post" action="{{ route('invoices.loyalty', [$inv, 'coupon']) }}" class="input-group input-group-sm">@csrf
                        <input name="code" class="form-control font-monospace text-uppercase" placeholder="{{ __('Coupon code') }}" required aria-label="{{ __('Coupon code') }}">
                        <button class="btn btn-outline-secondary">{{ __('Apply') }}</button></form>
                @endcan
            @elseif($inv->coupon_id)
                <div class="text-success"><i class="bi bi-ticket-perforated"></i> {{ __('Coupon applied') }}</div>
            @endif
        @else
            @if($earned)<div class="mb-2 text-success"><i class="bi bi-plus-circle"></i> {{ __(':n points earned with this invoice.', ['n' => number_format((float) $earned)]) }}</div>@endif
            @if(\App\Support\Money::isPositive($due))
                @can('payments.create')
                    @if($points >= (int) setting('loyalty.min_redeem', 100))
                        <form method="post" action="{{ route('invoices.loyalty', [$inv, 'points']) }}" class="mb-2">@csrf
                            <label class="form-label mb-0" for="rp">{{ __('Pay with points (:n available, worth :v)', ['n' => number_format($points), 'v' => money($points * $pv)]) }}</label>
                            <div class="input-group input-group-sm"><input id="rp" type="number" name="points" min="{{ (int) setting('loyalty.min_redeem', 100) }}" max="{{ (int) min($points, floor((float) $due / max($pv, 0.000001))) }}" value="{{ (int) min($points, floor((float) $due / max($pv, 0.000001))) }}" class="form-control" required>
                                <button class="btn btn-outline-warning">{{ __('Redeem') }}</button></div>
                        </form>
                    @endif
                    <form method="post" action="{{ route('invoices.loyalty', [$inv, 'gift-card']) }}">@csrf
                        <label class="form-label mb-0" for="gc">{{ __('Pay with a gift card') }}</label>
                        <div class="input-group input-group-sm"><input id="gc" name="code" class="form-control font-monospace text-uppercase" placeholder="GC-XXXX-XXXX" required data-scan-target>
                            <button class="btn btn-outline-secondary">{{ __('Apply') }}</button></div>
                    </form>
                @endcan
            @endif
        @endif
    </div>
</div>
@endif
