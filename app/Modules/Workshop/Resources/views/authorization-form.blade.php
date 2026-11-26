@extends('layouts.app')
@section('title', __('New authorization'))
@section('content')
<x-page-header :title="__('New handover authorization')" icon="bi-person-badge" :breadcrumbs="[__('Handover Authorizations') => route('handover-authorizations.index'), __('New')]"
    :subtitle="__('The vehicle owner authorises another person to drop off or pick up the vehicle. It becomes usable after the owner approves it.')"/>

<form method="post" action="{{ route('handover-authorizations.store') }}" class="row g-3">@csrf
    @if($jc)<input type="hidden" name="job_card_id" value="{{ $jc->id }}">@endif
    <div class="col-xl-7">
        <div class="card mb-3"><div class="card-header"><i class="bi bi-car-front"></i> 1. {{ __('Vehicle and owner') }}</div><div class="card-body">
            @if($vehicle)
                <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                <div class="fw-semibold"><span dir="ltr">{{ $vehicle->plate() }}</span> · {{ $vehicle->title() }}</div>
                <div class="small text-body-secondary">{{ __('Owner') }}: {{ $vehicle->customer?->name }} · <bdi dir="ltr">{{ $vehicle->customer?->phone }}</bdi>@if($jc) · {{ __('Job card') }} {{ $jc->number }}@endif</div>
            @else
                <label class="form-label required">{{ __('Vehicle') }}</label>
                <x-lookup name="vehicle_id" type="vehicles" :placeholder="__('Search plate / VIN...')"/>
                @error('vehicle_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('The authorization is given by the registered owner of the vehicle.') }}</div>
            @endif
        </div></div>

        <div class="card mb-3"><div class="card-header"><i class="bi bi-person-vcard"></i> 2. {{ __('Authorized person') }}</div><div class="card-body row g-2">
            <div class="col-md-6"><label class="form-label required" for="person_name">{{ __('Full name') }}</label><input id="person_name" name="person_name" class="form-control @error('person_name') is-invalid @enderror" value="{{ old('person_name') }}" required>
                @error('person_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="relationship">{{ __('Relationship to the owner') }}</label>
                <select id="relationship" name="relationship" class="form-select"><option value="">—</option>@foreach(\App\Modules\Workshop\Models\HandoverAuthorization::RELATIONSHIPS as $r)<option value="{{ $r }}" @selected(old('relationship') === $r)>{{ status_label($r) }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label" for="id_type">{{ __('ID type') }}</label>
                <select id="id_type" name="id_type" class="form-select">@foreach(\App\Modules\Workshop\Models\HandoverAuthorization::ID_TYPES as $t)<option value="{{ $t }}" @selected(old('id_type', 'civil_id') === $t)>{{ status_label($t) }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label required" for="id_number">{{ __('ID number') }}</label><input id="id_number" name="id_number" class="form-control @error('id_number') is-invalid @enderror" dir="ltr" value="{{ old('id_number') }}" required>
                @error('id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label" for="person_phone">{{ __('Phone') }}</label><input id="person_phone" name="person_phone" class="form-control" dir="ltr" value="{{ old('person_phone') }}" placeholder="+968 9xxxxxxx"></div>
        </div></div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-3"><div class="card-header"><i class="bi bi-shield-check"></i> 3. {{ __('Scope and validity') }}</div><div class="card-body row g-2">
            <div class="col-12"><label class="form-label required">{{ __('Allowed to') }}</label>
                @foreach(['pick_up' => 'bi-box-arrow-up', 'drop_off' => 'bi-box-arrow-in-down', 'both' => 'bi-arrow-left-right'] as $t => $icon)
                    <div class="form-check"><input class="form-check-input" type="radio" name="type" id="type_{{ $t }}" value="{{ $t }}" @checked(old('type', $jc ? 'pick_up' : 'both') === $t)><label class="form-check-label" for="type_{{ $t }}"><i class="bi {{ $icon }}"></i> {{ status_label($t) }}</label></div>
                @endforeach</div>
            <div class="col-md-6"><label class="form-label" for="valid_from">{{ __('Valid from') }}</label><input type="datetime-local" id="valid_from" name="valid_from" class="form-control" value="{{ old('valid_from', now()->format('Y-m-d\TH:i')) }}"></div>
            <div class="col-md-6"><label class="form-label" for="valid_until">{{ __('Valid until') }}</label><input type="datetime-local" id="valid_until" name="valid_until" class="form-control @error('valid_until') is-invalid @enderror" value="{{ old('valid_until', now()->addDays(7)->setTime(23, 59)->format('Y-m-d\TH:i')) }}">
                @error('valid_until')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="single_use" value="1" id="single_use" @checked(old('single_use', true))><label class="form-check-label" for="single_use">{{ __('Single use (ends after the first handover)') }}</label></div></div>
            <div class="col-12"><label class="form-label" for="notes">{{ __('Notes') }}</label><textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea></div>
        </div></div>
        <div class="alert alert-info small"><i class="bi bi-info-circle"></i> {{ __('After saving, the owner approves the authorization with a code sent to the owner\'s phone, or by signing the printed authorization letter.') }}</div>
        <button class="btn btn-primary btn-lg w-100"><i class="bi bi-check2-circle"></i> {{ __('Issue authorization') }}</button>
    </div>
</form>
@endsection
