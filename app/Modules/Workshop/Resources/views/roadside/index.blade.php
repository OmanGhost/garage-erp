@extends('layouts.app')
@section('title', __('Roadside assistance'))
@section('content')
@php
    $typeIcon = ['tow' => 'bi-truck-flatbed', 'battery' => 'bi-battery-charging', 'tyre' => 'bi-life-preserver', 'fuel' => 'bi-fuel-pump', 'lockout' => 'bi-unlock', 'jumpstart' => 'bi-lightning-charge', 'other' => 'bi-tools'];
@endphp
<x-page-header :title="__('Roadside assistance')" icon="bi-truck-flatbed" :breadcrumbs="[__('Workshop'), __('Roadside assistance')]">
    @can('roadside.create')<button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#rsaNew"><i class="bi bi-telephone-plus"></i> {{ __('New call') }}</button>@endcan
    @can('settings.update')<button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#rsaPricing"><i class="bi bi-sliders"></i> {{ __('Pricing') }}</button>@endcan
</x-page-header>

<div class="row g-3 mb-3">
    @foreach([
        [__('Open calls'), $open->count(), 'bi-broadcast', 'danger'],
        [__('Completed this month'), $stats['month'], 'bi-check2-circle', 'success'],
        [__('Billed this month'), money($stats['revenue']), 'bi-cash-coin', 'primary'],
        [__('Covered by membership'), $stats['covered'], 'bi-person-vcard', 'warning'],
        [__('Average response'), $stats['response'] ? round($stats['response']).' '.__('min') : '—', 'bi-stopwatch', 'info'],
    ] as [$l, $v, $i, $c])
        <div class="col-6 col-lg"><div class="card h-100"><div class="card-body d-flex align-items-center gap-2">
            <i class="bi {{ $i }} fs-3 text-{{ $c }}"></i><div><div class="small text-body-secondary">{{ $l }}</div><div class="fs-5 fw-bold">{{ $v }}</div></div>
        </div></div></div>
    @endforeach
</div>

@can('roadside.create')
<div class="collapse mb-3 {{ $errors->any() ? 'show' : '' }}" id="rsaNew"><form method="post" action="{{ route('roadside.store') }}" class="card">@csrf
    <div class="card-header fw-semibold"><i class="bi bi-telephone-plus me-1"></i>{{ __('New call') }}</div>
    <div class="card-body row g-2">
        <div class="col-md-3"><label class="form-label small">{{ __('Caller name') }}</label><input name="caller_name" class="form-control" required maxlength="120" value="{{ old('caller_name') }}"></div>
        <div class="col-md-3"><label class="form-label small">{{ __('Phone') }}</label><input name="caller_phone" class="form-control" required maxlength="30" dir="ltr" value="{{ old('caller_phone') }}"></div>
        <div class="col-md-2"><label class="form-label small">{{ __('Plate') }}</label><input name="plate" class="form-control" maxlength="30" value="{{ old('plate') }}"></div>
        <div class="col-md-4"><label class="form-label small">{{ __('Service') }}</label>
            <select name="service_type" class="form-select">@foreach(\App\Modules\Workshop\Models\RoadsideRequest::TYPES as $t)<option value="{{ $t }}" @selected(old('service_type') === $t)>{{ status_label($t) }} — {{ money($pricing[$t]['base']) }}</option>@endforeach</select></div>
        <div class="col-md-5"><label class="form-label small">{{ __('Location') }}</label><input name="location_text" class="form-control" maxlength="190" value="{{ old('location_text') }}" placeholder="{{ __('Area, road, landmark…') }}"></div>
        <div class="col-md-4"><label class="form-label small">{{ __('Destination (for tow)') }}</label><input name="destination_text" class="form-control" maxlength="190" value="{{ old('destination_text') }}"></div>
        <div class="col-md-3"><label class="form-label small">{{ __('Notes') }}</label><input name="notes" class="form-control" maxlength="1000" value="{{ old('notes') }}"></div>
        <div class="col-12 form-text">{{ __('The customer is matched by phone. After a driver is assigned the customer receives a link to share their exact location.') }}</div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('Create') }}</button></div>
</form></div>
@endcan

@can('settings.update')
<div class="collapse mb-3" id="rsaPricing"><form method="post" action="{{ route('roadside.settings') }}" class="card">@csrf
    <div class="card-header fw-semibold"><i class="bi bi-sliders me-1"></i>{{ __('Pricing') }}</div>
    <div class="card-body row g-2">
        @foreach($pricing as $t => $p)
            <div class="col-6 col-md-3 col-xl"><label class="form-label small">{{ __('Base fee') }} — {{ status_label($t) }}</label><input type="number" step="0.001" min="0" name="base_{{ $t }}" class="form-control" value="{{ $p['base'] }}" required></div>
        @endforeach
        <div class="col-6 col-md-3"><label class="form-label small">{{ __('Rate per km') }}</label><input type="number" step="0.001" min="0" name="km_rate" class="form-control" value="{{ $pricing['tow']['km_rate'] }}" required></div>
        <div class="col-6 col-md-3"><label class="form-label small">{{ __('Included km') }}</label><input type="number" step="0.1" min="0" name="free_km" class="form-control" value="{{ $pricing['tow']['free_km'] }}" required></div>
        <div class="col-12 form-text">{{ __('Price = base fee + (distance − included km) × rate per km + extras. A membership roadside call covers the base fee.') }}</div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Save') }}</button></div>
</form></div>
@endcan

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-broadcast text-danger me-1"></i>{{ __('Open calls') }}</div>
            <div class="list-group list-group-flush">
                @forelse($open as $r)
                    <a href="{{ route('roadside.show', $r) }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-center">
                        <i class="bi {{ $typeIcon[$r->service_type] ?? 'bi-tools' }} fs-3 text-primary"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $r->number }} · {{ status_label($r->service_type) }} @if($r->membership_id)<span class="badge text-bg-warning"><i class="bi bi-person-vcard"></i> {{ __('Member') }}</span>@endif</div>
                            <div class="small">{{ $r->caller_name }} <span dir="ltr">{{ $r->caller_phone }}</span>@if($r->plate) · {{ $r->plate }}@endif</div>
                            <div class="small text-body-secondary">@if($r->lat)<i class="bi bi-geo-alt-fill text-success"></i>@endif {{ $r->location_text ?: __('Location not shared yet') }}</div>
                        </div>
                        <div class="text-end small">
                            {!! status_badge($r->status) !!}
                            <div class="text-body-secondary mt-1">{{ $r->driver?->name ?? __('No driver') }}</div>
                            <div class="text-body-secondary">{{ $r->created_at->diffForHumans() }}</div>
                        </div>
                    </a>
                @empty
                    <div class="empty-state p-4"><i class="bi bi-broadcast"></i>{{ __('No open calls.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-clock-history me-1"></i>{{ __('Recent') }}</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <tbody>
                @forelse($recent as $r)
                    <tr>
                        <td><a href="{{ route('roadside.show', $r) }}" class="fw-semibold">{{ $r->number }}</a><div class="small text-body-secondary">{{ status_label($r->service_type) }} · {{ $r->caller_name }}</div></td>
                        <td class="small">{{ $r->completed_at?->format('m-d H:i') ?? $r->updated_at->format('m-d H:i') }}@if($r->responseMinutes())<div class="text-body-secondary">{{ $r->responseMinutes() }} {{ __('min') }}</div>@endif</td>
                        <td class="text-end font-monospace">{{ $r->status === 'completed' ? money($r->total, false) : '' }}@if($r->covered)<div><span class="badge text-bg-warning">{{ __('Member') }}</span></div>@endif</td>
                        <td class="text-end">{!! status_badge($r->status) !!}</td>
                    </tr>
                @empty
                    <tr><td class="text-body-secondary p-3">{{ __('Nothing yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
