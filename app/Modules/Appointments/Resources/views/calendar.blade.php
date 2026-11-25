@extends('layouts.app')
@section('title', __('Appointments'))
@section('content')
@php
    $prev = match($view) { 'day' => $date->copy()->subDay(), 'month' => $date->copy()->subMonth(), default => $date->copy()->subWeek() };
    $next = match($view) { 'day' => $date->copy()->addDay(), 'month' => $date->copy()->addMonth(), default => $date->copy()->addWeek() };
    $byDay = $appointments->groupBy(fn($a) => $a->starts_at->toDateString());
@endphp
<x-page-header :title="__('Appointments calendar')" icon="bi-calendar-week" :breadcrumbs="[__('Appointments')]">
    <div class="btn-group">@foreach(['day' => __('Day'), 'week' => __('Week'), 'month' => __('Month')] as $k => $l)<a href="{{ route('appointments.calendar', ['view' => $k, 'date' => $date->toDateString()]) }}" class="btn btn-outline-secondary {{ $view === $k ? 'active' : '' }}">{{ $l }}</a>@endforeach</div>
    <div class="btn-group"><a href="{{ route('appointments.calendar', ['view' => $view, 'date' => $prev->toDateString()]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-{{ is_rtl() ? 'right' : 'left' }}"></i></a>
        <a href="{{ route('appointments.calendar', ['view' => $view]) }}" class="btn btn-outline-secondary">{{ __('Today') }}</a>
        <a href="{{ route('appointments.calendar', ['view' => $view, 'date' => $next->toDateString()]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-{{ is_rtl() ? 'left' : 'right' }}"></i></a></div>
    @can('appointments.create')<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookModal"><i class="bi bi-plus-lg"></i> {{ __('Book appointment') }}</button>@endcan
</x-page-header>
<div class="card"><div class="card-body">
    <h5 class="mb-3">{{ $view === 'month' ? $date->translatedFormat('F Y') : $from->format('Y-m-d').' → '.$to->format('Y-m-d') }}</h5>
    @if($view === 'day')
        <div class="list-group">
            @forelse($appointments as $a)
                <a href="{{ route('appointments.show', $a) }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-center">
                    <b class="font-monospace">{{ $a->starts_at->format('H:i') }}–{{ $a->ends_at->format('H:i') }}</b>
                    <span>{{ $a->customer->name }} · {{ $a->vehicle?->plate() }}</span><span class="small text-body-secondary">{{ $a->department?->displayName() }} {{ $a->technician?->name }}</span>
                    <span class="ms-auto">{!! status_badge($a->status) !!}</span></a>
            @empty<div class="empty-state"><i class="bi bi-calendar-x"></i>{{ __('No appointments.') }}</div>@endforelse
        </div>
    @else
        <div class="cal-grid">
            @foreach([__('Saturday'), __('Sunday'), __('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday')] as $dn)<div class="cal-cell fw-bold text-center py-1" style="min-height:auto">{{ $dn }}</div>@endforeach
            @for($d = $from->copy(); $d->lte($to); $d->addDay())
                @php $list = $byDay[$d->toDateString()] ?? collect(); @endphp
                <div class="cal-cell {{ $view === 'month' && $d->month !== $date->month ? 'other' : '' }} {{ $d->isToday() ? 'today' : '' }}">
                    <div class="d-flex justify-content-between"><a href="{{ route('appointments.calendar', ['view' => 'day', 'date' => $d->toDateString()]) }}" class="fw-bold text-decoration-none">{{ $d->day }}</a>
                        @if($capacity)<span class="small {{ $list->count() >= $capacity ? 'text-danger' : 'text-body-secondary' }}">{{ $list->count() }}/{{ $capacity }}</span>@endif</div>
                    @foreach($list as $a)<a href="{{ route('appointments.show', $a) }}" class="cal-event st-{{ $a->status }}" title="{{ $a->customer->name }}">{{ $a->starts_at->format('H:i') }} {{ $a->vehicle?->plate() ?? $a->customer->name }}</a>@endforeach
                </div>
            @endfor
        </div>
    @endif
</div></div>

@can('appointments.create')
<div class="modal fade" id="bookModal" tabindex="-1"><div class="modal-dialog modal-lg"><form method="post" action="{{ route('appointments.store') }}" class="modal-content">@csrf <x-idem/>
    <div class="modal-header"><h5 class="modal-title">{{ __('Book appointment') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-2">
        <div class="col-md-6"><label class="form-label required">{{ __('Customer') }}</label><x-lookup name="customer_id" type="customers" id="lk_appt_cust" required/></div>
        <div class="col-md-6"><label class="form-label">{{ __('Vehicle') }}</label><x-lookup name="vehicle_id" type="vehicles" params="customer_id=#lk_appt_cust"/></div>
        <div class="col-md-4"><label class="form-label required">{{ __('Start') }}</label><input type="datetime-local" name="starts_at" class="form-control" value="{{ $date->copy()->setTime(9, 0)->format('Y-m-d\TH:i') }}" required></div>
        <div class="col-md-2"><label class="form-label">{{ __('Minutes') }}</label><input type="number" name="duration" value="60" min="15" step="15" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">{{ __('Service') }}</label><x-lookup name="service_id" type="services"/></div>
        <div class="col-md-4"><label class="form-label">{{ __('Department') }}</label><select name="department_id" class="form-select"><option value="">—</option>@foreach($departments as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">{{ __('Technician') }}</label><select name="technician_id" class="form-select"><option value="">—</option>@foreach($technicians as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">{{ __('Bay') }}</label><select name="bay_id" class="form-select"><option value="">—</option>@foreach($bays as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">{{ __('Source') }}</label><select name="source" class="form-select"><option value="staff">{{ __('Walk-in / staff') }}</option><option value="phone">{{ __('Phone') }}</option><option value="whatsapp">WhatsApp</option></select></div>
        <div class="col-md-8"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="notify" value="1" id="nt" checked><label class="form-check-label" for="nt">{{ __('Send confirmation to the customer') }}</label></div></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">{{ __('Book') }}</button></div>
</form></div></div>
@endcan
@endsection
