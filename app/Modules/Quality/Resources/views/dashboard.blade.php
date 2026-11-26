@extends('layouts.app')
@section('title', __('Quality management'))
@section('content')
@php
    $pct = fn ($v) => $v === null ? '—' : $v.'%';
    $maxCat = max(1, (int) ($k['ncr_by_category']->max('n') ?? 1));
@endphp
<x-page-header :title="__('Quality management')" icon="bi-award" :breadcrumbs="[__('Quality'), __('Quality management')]">
    <form class="d-flex gap-1"><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm" aria-label="{{ __('From') }}"><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm" aria-label="{{ __('To') }}"><button class="btn btn-sm btn-outline-secondary">{{ __('Apply') }}</button></form>
    @can('nonconformities.create')<a href="{{ route('crud.create', 'nonconformities') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New NCR') }}</a>@endcan
</x-page-header>

<div class="row g-2 mb-3">
    @foreach([
        [__('First-time fix'), $pct($k['first_time_fix']), __(':n comebacks', ['n' => $k['comebacks']->count()]), 'bi-bullseye', 'success'],
        [__('QC pass rate'), $pct($k['qc_pass_rate']), __(':n checks', ['n' => $k['qc_total']]), 'bi-patch-check', 'primary'],
        [__('Customer satisfaction'), $k['csat'] !== null ? $k['csat'].' / 5' : '—', $k['csat_pct'] !== null ? __(':p% rated 4–5 (:n ratings)', ['p' => $k['csat_pct'], 'n' => $k['ratings']]) : __('No ratings'), 'bi-emoji-smile', 'info'],
        [__('Open nonconformities'), $k['open_ncr'], __(':n overdue actions', ['n' => $k['overdue_capa']]), 'bi-exclamation-diamond', $k['overdue_capa'] ? 'danger' : 'warning'],
        [__('Internal audit score'), $k['audits'] !== null ? round($k['audits'], 1).'%' : '—', __('Completed audits in the period'), 'bi-clipboard2-data', 'secondary'],
    ] as [$l, $v, $sub, $icon, $c])
        <div class="col-6 col-lg"><div class="card kpi h-100"><div class="card-body d-flex align-items-center gap-3">
            <span class="kpi-icon bg-{{ $c }}-subtle text-{{ $c }}"><i class="bi {{ $icon }}"></i></span>
            <div class="min-w-0"><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-5">{{ $v }}</div><div class="small text-body-secondary">{{ $sub }}</div></div>
        </div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-bar-chart me-1"></i>{{ __('Causes of nonconformities (Pareto)') }}</div>
            <div class="card-body">
                @forelse($k['ncr_by_category'] as $r)
                    <div class="d-flex align-items-center gap-2 small mb-1"><span style="width:32%">{{ status_label($r->category) }}</span>
                        <div class="progress flex-grow-1" style="height:10px" role="progressbar" aria-valuenow="{{ $r->n }}" aria-valuemin="0" aria-valuemax="{{ $maxCat }}" aria-label="{{ status_label($r->category) }}"><div class="progress-bar bg-danger" style="width:{{ round($r->n * 100 / $maxCat) }}%"></div></div>
                        <span class="font-monospace" style="width:2.5rem">{{ $r->n }}</span><span class="text-body-secondary font-monospace" style="width:6rem">{{ money($r->cost, false) }}</span></div>
                @empty<div class="text-body-secondary small">{{ __('No nonconformities in the period.') }}</div>@endforelse
                @if($k['ncr_by_source']->isNotEmpty())
                    <div class="d-flex flex-wrap gap-1 mt-2">@foreach($k['ncr_by_source'] as $s => $n)<span class="badge text-bg-light">{{ status_label($s) }}: {{ $n }}</span>@endforeach</div>
                @endif
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6"><div class="card h-100"><div class="card-header small fw-semibold">{{ __('Recurring warranty failures') }}</div><ul class="list-group list-group-flush small">
                @forelse($k['repeat_parts'] as $r)<li class="list-group-item d-flex"><span class="text-truncate">{{ $r->d }}</span><span class="ms-auto badge text-bg-danger">{{ $r->n }}</span></li>@empty<li class="list-group-item text-body-secondary">—</li>@endforelse
            </ul></div></div>
            <div class="col-md-6"><div class="card h-100"><div class="card-header small fw-semibold">{{ __('Nonconformities per technician') }}</div><ul class="list-group list-group-flush small">
                @forelse($k['ncr_by_technician'] as $r)<li class="list-group-item d-flex"><span>{{ $r['name'] }}</span><span class="ms-auto badge text-bg-warning">{{ $r['n'] }}</span></li>@empty<li class="list-group-item text-body-secondary">—</li>@endforelse
            </ul></div></div>
        </div>
        <div class="card mb-3"><div class="card-header small fw-semibold d-flex">{{ __('Supplier quality') }} @can('supplier_evaluations.view')<a href="{{ route('crud.index', 'supplier-evaluations') }}" class="ms-auto">{{ __('Supplier evaluations') }}</a>@endcan</div><ul class="list-group list-group-flush small">
            @forelse($k['suppliers'] as $s)<li class="list-group-item d-flex"><a href="{{ route('suppliers.show', $s->id) }}">{{ $s->name }}</a><span class="ms-auto badge text-bg-secondary">{{ $s->n }} NCR</span></li>@empty<li class="list-group-item text-body-secondary">{{ __('No supplier nonconformities.') }}</li>@endforelse
        </ul></div>
    </div>

    <div class="col-xl-6">
        <div class="card mb-3">
            <div class="card-header d-flex"><i class="bi bi-exclamation-diamond me-1"></i>{{ __('Open nonconformities') }}<a href="{{ route('crud.index', 'nonconformities') }}" class="ms-auto small">{{ __('All') }}</a></div>
            <ul class="list-group list-group-flush small">
                @forelse($openNcrs as $n)
                    <li class="list-group-item d-flex align-items-center gap-2"><a href="{{ route('quality.ncr.show', $n) }}" class="fw-semibold">{{ $n->number }}</a><span class="text-truncate">{{ $n->title }}</span>
                        <span class="ms-auto badge text-bg-{{ ['critical' => 'danger', 'major' => 'warning', 'minor' => 'secondary'][$n->severity] ?? 'secondary' }}">{{ status_label($n->severity) }}</span>{!! status_badge($n->status) !!}</li>
                @empty<li class="list-group-item text-body-secondary">{{ __('No open nonconformities.') }}</li>@endforelse
            </ul>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-alarm me-1 text-danger"></i>{{ __('Overdue actions') }}</div>
            <ul class="list-group list-group-flush small">
                @forelse($overdue as $a)
                    <li class="list-group-item"><a href="{{ route('quality.ncr.show', $a->nonconformity_id) }}">{{ $a->nonconformity?->number }}</a> · {{ $a->description }}<div class="text-danger">{{ $a->owner?->name }} · {{ __('Due') }} {{ $a->due_date?->format('Y-m-d') }}</div></li>
                @empty<li class="list-group-item text-body-secondary">{{ __('No overdue actions.') }}</li>@endforelse
            </ul>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Comebacks (repeat repair within :d days)', ['d' => \App\Modules\Quality\Services\QualityService::COMEBACK_DAYS]) }}</div>
            <ul class="list-group list-group-flush small">
                @forelse($k['comebacks']->take(10) as $jc)
                    <li class="list-group-item d-flex align-items-center gap-2"><a href="{{ route('job-cards.show', $jc) }}">{{ $jc->number }}</a><span class="font-monospace">{{ $jc->vehicle?->plate() }}</span><span class="text-body-secondary">{{ $jc->received_at?->format('Y-m-d') }}</span>
                        @can('nonconformities.create')<form method="post" action="{{ route('quality.ncr.from-job', $jc) }}" class="ms-auto">@csrf<input type="hidden" name="source" value="comeback"><button class="btn btn-sm btn-outline-danger py-0">{{ __('Open NCR') }}</button></form>@endcan</li>
                @empty<li class="list-group-item text-body-secondary">{{ __('No comebacks in the period.') }}</li>@endforelse
            </ul>
        </div>
        <div class="row g-3">
            <div class="col-md-6"><div class="card h-100"><div class="card-header small fw-semibold d-flex">{{ __('Procedures to review') }}<a href="{{ route('crud.index', 'quality-sops') }}" class="ms-auto">{{ __('SOP') }}</a></div><ul class="list-group list-group-flush small">
                @forelse($sopsDue as $s)<li class="list-group-item">{{ $s->code }} · {{ $s->title }}<div class="{{ $s->review_date->isPast() ? 'text-danger' : 'text-body-secondary' }}">{{ $s->review_date->format('Y-m-d') }}</div></li>@empty<li class="list-group-item text-body-secondary">—</li>@endforelse
            </ul></div></div>
            <div class="col-md-6"><div class="card h-100"><div class="card-header small fw-semibold d-flex">{{ __('Planned audits') }}<a href="{{ route('crud.index', 'quality-audits') }}" class="ms-auto">{{ __('All') }}</a></div><ul class="list-group list-group-flush small">
                @forelse($audits as $a)<li class="list-group-item"><a href="{{ route('quality.audits.show', $a) }}">{{ $a->number }}</a> · {{ $a->title }}<div class="text-body-secondary">{{ $a->planned_date->format('Y-m-d') }} · {{ status_label($a->status) }}</div></li>@empty<li class="list-group-item text-body-secondary">—</li>@endforelse
            </ul></div></div>
        </div>
    </div>
</div>
@endsection
