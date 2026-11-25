@extends('layouts.portal')
@section('title', __('Book maintenance'))
@section('content')
<h5 class="mb-3">{{ __('Book a maintenance appointment') }}</h5>
<form method="get" class="mb-3"><label class="form-label">{{ __('Date') }}</label><input type="date" name="date" value="{{ $date }}" min="{{ now()->toDateString() }}" class="form-control" onchange="this.form.submit()"></form>
<form method="post" action="{{ route('portal.appointments.store') }}" class="card">@csrf<div class="card-body">
    <input type="hidden" name="date" value="{{ $date }}">
    <label class="form-label">{{ __('Vehicle') }}</label>
    <select name="vehicle_id" class="form-select mb-2" required>@foreach($vehicles as $v)<option value="{{ $v->id }}">{{ $v->label() }}</option>@endforeach</select>
    <label class="form-label">{{ __('Service') }}</label>
    <select name="service_id" class="form-select mb-2"><option value="">{{ __('General check / not sure') }}</option>@foreach($services as $s)<option value="{{ $s->id }}">{{ $s->displayName() }}</option>@endforeach</select>
    <label class="form-label">{{ __('Available times') }}</label>
    <div class="d-flex flex-wrap gap-2 mb-2">
        @forelse($slots as $t)<input type="radio" class="btn-check" name="time" id="t{{ $loop->index }}" value="{{ $t }}" required><label class="btn btn-outline-primary btn-sm" for="t{{ $loop->index }}">{{ $t }}</label>
        @empty<div class="text-body-secondary small">{{ __('No free slots on this date. Please choose another day.') }}</div>@endforelse
    </div>
    <textarea name="notes" class="form-control mb-2" rows="2" placeholder="{{ __('Describe the problem (optional)') }}"></textarea>
    <button class="btn btn-primary w-100" @disabled(empty($slots) || $vehicles->isEmpty())>{{ __('Confirm booking') }}</button>
    @if($vehicles->isEmpty())<div class="small text-danger mt-1">{{ __('Add a vehicle first.') }}</div>@endif
</div></form>
@endsection
