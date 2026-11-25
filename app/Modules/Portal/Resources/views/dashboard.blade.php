@extends('layouts.portal')
@section('title', __('My account'))
@section('content')
<h4 class="mb-3">{{ __('Welcome') }}, {{ $c->name }}</h4>
<div class="row g-3 mb-3">
    <div class="col-6"><a href="{{ route('portal.statement') }}" class="card kpi text-decoration-none"><div class="card-body"><div class="kpi-label">{{ __('Balance due') }}</div><div class="kpi-value {{ (float) $balance > 0 ? 'text-danger' : 'text-success' }}">{{ money($balance) }}</div></div></a></div>
    <div class="col-6"><a href="{{ route('portal.appointments.create') }}" class="card kpi text-decoration-none bg-brand"><div class="card-body text-white"><div class="small">{{ __('Book maintenance') }}</div><div class="fs-4"><i class="bi bi-calendar-plus"></i></div></div></a></div>
</div>
@foreach($pendingQuotes as $q)
    <a href="{{ route('portal.quotations.show', $q) }}" class="alert alert-warning d-flex align-items-center gap-2 text-decoration-none"><i class="bi bi-file-earmark-text fs-4"></i><div><b>{{ __('Quotation waiting for your approval') }}</b><div class="small">{{ $q->number }} — {{ money($q->total) }}</div></div></a>
@endforeach
<h6 class="mt-3">{{ __('Vehicles in the workshop') }}</h6>
@forelse($active as $j)
    <a href="{{ route('portal.jobs.show', $j) }}" class="card mb-2 text-decoration-none"><div class="card-body">
        <div class="d-flex align-items-center gap-2"><span class="plate-badge">{{ $j->vehicle->plate() }}</span><span class="small">{{ $j->vehicle->title() }}</span><span class="ms-auto">{!! status_badge($j->status) !!}</span></div>
        <div class="progress mt-2" style="height:8px"><div class="progress-bar bg-success" style="width:{{ $j->progress }}%"></div></div>
        <div class="small text-body-secondary mt-1">{{ $j->number }} · {{ __('Expected') }}: {{ $j->promised_at?->format('Y-m-d H:i') ?? '—' }}</div>
    </div></a>
@empty
    <div class="text-body-secondary small mb-3">{{ __('No vehicles in the workshop right now.') }}</div>
@endforelse
@if($appointments->isNotEmpty())
<h6 class="mt-3">{{ __('Upcoming appointments') }}</h6>
<ul class="list-group mb-3">@foreach($appointments as $a)<li class="list-group-item d-flex"><span>{{ $a->starts_at->format('Y-m-d H:i') }}</span><span class="ms-auto">{!! status_badge($a->status) !!}</span></li>@endforeach</ul>
@endif
<h6 class="mt-3">{{ __('Service history') }}</h6>
<div class="list-group">@forelse($history as $j)
    <a href="{{ route('portal.jobs.show', $j) }}" class="list-group-item list-group-item-action d-flex"><span>{{ $j->vehicle->plate() }} · {{ $j->delivered_at?->format('Y-m-d') }}</span>
        <span class="ms-auto">@if($j->rating)@for($s = 1; $s <= 5; $s++)<i class="bi bi-star{{ $s <= $j->rating->rating ? '-fill text-warning' : '' }}"></i>@endfor @else<span class="badge text-bg-info">{{ __('Rate us') }}</span>@endif</span></a>
@empty<div class="list-group-item text-body-secondary small">—</div>@endforelse</div>
@endsection
