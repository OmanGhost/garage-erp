@extends('layouts.app')
@section('title', __('Roadside assistance').' '.$r->number)
@section('content')
@php
    $link = route('roadside.public', $r->public_token);
    $wa = 'https://wa.me/'.preg_replace('/\D+/', '', $r->caller_phone).'?text='.rawurlencode(__('Share your location and follow your roadside request :n here:', ['n' => $r->number]).' '.$link);
    $steps = [['new', __('Call received'), $r->created_at], ['assigned', __('Driver assigned'), $r->assigned_at], ['en_route', __('On the way'), $r->en_route_at], ['on_site', __('On site'), $r->arrived_at], ['completed', __('Completed'), $r->completed_at]];
    $memberLeft = $r->membership?->roadsideLeft();
@endphp
<x-page-header :title="$r->number" icon="bi-truck-flatbed" :breadcrumbs="[__('Workshop'), __('Roadside assistance'), $r->number]">
    <a href="{{ route('roadside.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> {{ __('Back') }}</a>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2"><h5 class="mb-0">{{ status_label($r->service_type) }}</h5><span class="ms-auto">{!! status_badge($r->status) !!}</span></div>
                <dl class="row small mb-0">
                    <dt class="col-4">{{ __('Caller') }}</dt><dd class="col-8">{{ $r->caller_name }} · <a href="tel:{{ $r->caller_phone }}" dir="ltr">{{ $r->caller_phone }}</a></dd>
                    <dt class="col-4">{{ __('Customer') }}</dt><dd class="col-8">@if($r->customer)<a href="{{ route('customers.show', $r->customer) }}">{{ $r->customer->name }}</a>@else — @endif</dd>
                    <dt class="col-4">{{ __('Vehicle') }}</dt><dd class="col-8">{{ $r->vehicle?->plate() ?? $r->plate ?? '—' }} {{ $r->vehicle?->title() }}</dd>
                    <dt class="col-4">{{ __('Location') }}</dt><dd class="col-8">{{ $r->location_text ?: '—' }}
                        @if($r->mapUrl())<div><a href="{{ $r->mapUrl() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success mt-1"><i class="bi bi-geo-alt-fill"></i> {{ __('Open in Google Maps') }}</a> <span class="text-body-secondary">{{ $r->location_at?->diffForHumans() }}</span></div>@endif</dd>
                    <dt class="col-4">{{ __('Destination') }}</dt><dd class="col-8">{{ $r->destination_text ?: '—' }}</dd>
                    @if($r->notes)<dt class="col-4">{{ __('Notes') }}</dt><dd class="col-8">{{ $r->notes }}</dd>@endif
                    @if($r->membership)<dt class="col-4">{{ __('Membership') }}</dt><dd class="col-8"><a href="{{ route('memberships.show', $r->membership) }}">{{ $r->membership->number }}</a> · {{ $r->membership->plan?->displayName() }} · {{ __(':n roadside calls left', ['n' => $memberLeft]) }}</dd>@endif
                    @if($r->cancel_reason)<dt class="col-4">{{ __('Cancel reason') }}</dt><dd class="col-8">{{ $r->cancel_reason }}</dd>@endif
                    @if($r->invoice)<dt class="col-4">{{ __('Invoice') }}</dt><dd class="col-8"><a href="{{ route('invoices.show', $r->invoice) }}">{{ $r->invoice->number }}</a> {!! status_badge($r->invoice->status) !!}</dd>@endif
                    @if($r->jobCard)<dt class="col-4">{{ __('Job card') }}</dt><dd class="col-8"><a href="{{ route('job-cards.show', $r->jobCard) }}">{{ $r->jobCard->number }}</a></dd>@endif
                </dl>
            </div>
        </div>

        @if($r->isOpen())
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-link-45deg me-1"></i>{{ __('Customer location link') }}</div>
                <div class="card-body">
                    <div class="input-group input-group-sm mb-2"><input class="form-control" value="{{ $link }}" readonly dir="ltr" aria-label="{{ __('Link') }}" onclick="this.select()">
                        <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-success"><i class="bi bi-whatsapp"></i> {{ __('Send on WhatsApp') }}</a></div>
                    <div class="form-text">{{ __('The customer opens the link to share their exact GPS location and follow the request. It stops accepting locations once the call is closed.') }}</div>
                </div>
            </div>
        @endif

        @can('roadside.update')
        @if($r->isOpen())
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-send me-1"></i>{{ __('Dispatch') }}</div>
                <div class="card-body">
                    <form method="post" action="{{ route('roadside.action', [$r, 'assign']) }}" class="input-group mb-2">@csrf
                        <select name="assigned_to" class="form-select" aria-label="{{ __('Driver') }}">@foreach($drivers as $id => $n)<option value="{{ $id }}" @selected($r->assigned_to === $id)>{{ $n }}</option>@endforeach</select>
                        <button class="btn btn-primary"><i class="bi bi-person-check"></i> {{ $r->assigned_to ? __('Change driver') : __('Assign driver') }}</button>
                    </form>
                    @if($r->assigned_to)
                        <div class="d-flex gap-2 flex-wrap">
                            @if(in_array($r->status, ['assigned'], true))<form method="post" action="{{ route('roadside.action', [$r, 'en_route']) }}">@csrf<button class="btn btn-outline-primary"><i class="bi bi-signpost-2"></i> {{ __('On the way') }}</button></form>@endif
                            @if(in_array($r->status, ['assigned', 'en_route'], true))<form method="post" action="{{ route('roadside.action', [$r, 'on_site']) }}">@csrf<button class="btn btn-outline-primary"><i class="bi bi-geo-alt"></i> {{ __('Arrived on site') }}</button></form>@endif
                        </div>
                    @endif
                </div>
            </div>

            @if($r->assigned_to)
            <form method="post" action="{{ route('roadside.action', [$r, 'complete']) }}" class="card mb-3" x-data="{km: 0, extras: 0, member: {{ $r->membership_id && $memberLeft > 0 ? 'true' : 'false' }}, base: {{ (float) $r->base_fee }}, rate: {{ (float) $r->km_rate }}, free: {{ (float) $free_km }},
                    get dist() { return Math.max(0, (parseFloat(this.km) || 0) - this.free) * this.rate }, get total() { return this.base + this.dist + (parseFloat(this.extras) || 0) }, get due() { return this.total - (this.member ? this.base : 0) } }">@csrf
                <div class="card-header fw-semibold"><i class="bi bi-check2-circle me-1"></i>{{ __('Complete the call') }}</div>
                <div class="card-body row g-2">
                    <div class="col-md-4"><label class="form-label small">{{ __('Distance (km)') }}</label><input type="number" step="0.1" min="0" name="distance_km" class="form-control" x-model="km" required></div>
                    <div class="col-md-4"><label class="form-label small">{{ __('Extra charges') }}</label><input type="number" step="0.001" min="0" name="extra_charges" class="form-control" x-model="extras"></div>
                    <div class="col-md-4"><label class="form-label small">{{ __('Destination') }}</label><input name="destination_text" class="form-control" maxlength="190" value="{{ $r->destination_text }}"></div>
                    @if($r->membership_id && $memberLeft > 0)
                        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="use_membership" value="1" id="useM" x-model="member"><label class="form-check-label" for="useM">{{ __('Use a membership roadside call (covers the base fee; distance and extras are still charged)') }}</label></div></div>
                    @endif
                    @if($r->service_type === 'tow' && $r->vehicle_id)
                        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="to_workshop" value="1" id="toW" checked><label class="form-check-label" for="toW">{{ __('Towed into our workshop — open a job card') }}</label></div></div>
                    @endif
                    <div class="col-12">
                        <table class="table table-sm mb-0 small">
                            <tr><td>{{ __('Base fee') }}</td><td class="text-end font-monospace" x-text="base.toFixed(3)"></td></tr>
                            <tr><td>{{ __('Distance beyond :km km', ['km' => $free_km]) }} × {{ money($r->km_rate, false) }}</td><td class="text-end font-monospace" x-text="dist.toFixed(3)"></td></tr>
                            <tr><td>{{ __('Extra charges') }}</td><td class="text-end font-monospace" x-text="(parseFloat(extras) || 0).toFixed(3)"></td></tr>
                            <tr x-show="member"><td>{{ __('Covered by membership') }}</td><td class="text-end font-monospace text-success" x-text="'-' + base.toFixed(3)"></td></tr>
                            <tr class="fw-bold"><td>{{ __('To invoice (before VAT)') }}</td><td class="text-end font-monospace" x-text="due.toFixed(3)"></td></tr>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-success"><i class="bi bi-check2-circle"></i> {{ __('Complete') }}</button></div>
            </form>
            @endif

            <form method="post" action="{{ route('roadside.action', [$r, 'cancel']) }}" class="input-group input-group-sm" data-confirm="{{ __('Cancel this request?') }}">@csrf
                <input name="reason" class="form-control" placeholder="{{ __('Cancel reason') }}" required maxlength="190" aria-label="{{ __('Cancel reason') }}">
                <button class="btn btn-outline-danger"><i class="bi bi-x-lg"></i> {{ __('Cancel request') }}</button>
            </form>
        @endif
        @endcan
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-clock-history me-1"></i>{{ __('Timeline') }}</div>
            <div class="card-body">
                <ul class="timeline mb-0">
                    @foreach($steps as [$k, $l, $at])
                        <li class="{{ $at ? '' : 'text-body-tertiary' }}"><b>{{ $l }}</b><div class="small text-body-secondary">{{ $at?->format('Y-m-d H:i') ?? '—' }}</div></li>
                    @endforeach
                </ul>
                @if($r->driver)<div class="mt-2 small">{{ __('Driver') }}: <b>{{ $r->driver->name }}</b></div>@endif
                @if($r->responseMinutes() !== null)<div class="small">{{ __('Response time') }}: <b>{{ $r->responseMinutes() }} {{ __('min') }}</b></div>@endif
                @if($r->status === 'completed')<div class="small">{{ __('Total') }}: <b>{{ money($r->total) }}</b> @if($r->covered)<span class="badge text-bg-warning">{{ __('Covered by membership') }}</span>@endif</div>@endif
            </div>
        </div>
    </div>
</div>
@endsection
