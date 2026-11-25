@extends('layouts.app')
@section('title', $a->number)
@section('content')
<x-page-header :title="__('Appointment').' '.$a->number" icon="bi-calendar-event" :breadcrumbs="[__('Appointments') => route('appointments.calendar'), $a->number]" :subtitle="status_badge($a->status)">
    @if(in_array($a->status, ['scheduled', 'confirmed']))
        @can('reception.create')<a href="{{ route('reception.create', ['appointment_id' => $a->id]) }}" class="btn btn-primary"><i class="bi bi-box-arrow-in-down"></i> {{ __('Check in vehicle') }}</a>@endcan
        @can('appointments.update')
            <form method="post" action="{{ route('appointments.status', $a) }}">@csrf <x-idem/><input type="hidden" name="status" value="no_show"><button class="btn btn-outline-warning">{{ __('No show') }}</button></form>
            <form method="post" action="{{ route('appointments.status', $a) }}" data-confirm="{{ __('Cancel the appointment?') }}">@csrf <x-idem/><input type="hidden" name="status" value="cancelled"><button class="btn btn-outline-danger">{{ __('Cancel') }}</button></form>
        @endcan
    @endif
    @if($a->jobCard)<a href="{{ route('job-cards.show', $a->jobCard) }}" class="btn btn-outline-secondary">{{ $a->jobCard->number }}</a>@endif
</x-page-header>
<div class="row g-3">
    <div class="col-lg-6"><div class="card"><div class="card-body">
        <dl class="row mb-0">
            <dt class="col-4">{{ __('Time') }}</dt><dd class="col-8">{{ $a->starts_at->format('Y-m-d H:i') }} → {{ $a->ends_at->format('H:i') }}</dd>
            <dt class="col-4">{{ __('Customer') }}</dt><dd class="col-8"><a href="{{ route('customers.show', $a->customer) }}">{{ $a->customer->name }}</a> <span dir="ltr">{{ $a->customer->phone }}</span></dd>
            <dt class="col-4">{{ __('Vehicle') }}</dt><dd class="col-8">{{ $a->vehicle?->label() ?? '—' }}</dd>
            <dt class="col-4">{{ __('Service') }}</dt><dd class="col-8">{{ $a->service?->displayName() ?? '—' }}</dd>
            <dt class="col-4">{{ __('Department') }}</dt><dd class="col-8">{{ $a->department?->displayName() ?? '—' }}</dd>
            <dt class="col-4">{{ __('Technician / bay') }}</dt><dd class="col-8">{{ $a->technician?->name ?? '—' }} / {{ $a->bay?->name ?? '—' }}</dd>
            <dt class="col-4">{{ __('Source') }}</dt><dd class="col-8">{{ status_label($a->source) }} · {{ __('Rescheduled') }} {{ $a->rescheduled_count }}×</dd>
            <dt class="col-4">{{ __('Notes') }}</dt><dd class="col-8">{{ $a->notes }}</dd>
        </dl>
    </div></div></div>
    @if(in_array($a->status, ['scheduled', 'confirmed']))@can('appointments.update')
    <div class="col-lg-6"><form method="post" action="{{ route('appointments.reschedule', $a) }}" class="card">@csrf <x-idem/><div class="card-header">{{ __('Reschedule') }}</div><div class="card-body d-flex gap-2">
        <input type="datetime-local" name="starts_at" value="{{ $a->starts_at->format('Y-m-d\TH:i') }}" class="form-control" required><button class="btn btn-primary">{{ __('Save') }}</button></div></form></div>
    @endcan @endif
</div>
@endsection
