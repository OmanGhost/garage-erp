@extends('layouts.app')
@section('title', $f->name)
@section('content')
<x-page-header :title="$f->name" icon="bi-truck-front" :breadcrumbs="[__('Fleets') => route('crud.index', 'fleets'), $f->name]"
    :subtitle="e($f->customer->name).' · '.__('Contract').' '.e($f->contract_number).' '.($f->contract_end ? '→ '.$f->contract_end->format('Y-m-d') : '').' · '.status_badge($f->status)">
    @can('fleets.update')<a href="{{ route('crud.edit', ['fleets', $f->id]) }}" class="btn btn-outline-primary">{{ __('Edit') }}</a>@endcan
    @can('invoices.create')
    <form method="post" action="{{ route('fleets.invoice', $f) }}" class="d-flex gap-1" data-confirm="{{ __('Create and post the consolidated invoice for the month?') }}">@csrf <x-idem/>
        <input type="month" name="month" value="{{ now()->format('Y-m') }}" class="form-control"><button class="btn btn-success text-nowrap">{{ __('Monthly invoice') }}</button></form>
    @endcan
</x-page-header>
<div class="row g-3 mb-3">
    @foreach([[__('Spend this month'), money($monthSpend)], [__('Monthly limit'), $f->monthly_spend_limit ? money($f->monthly_spend_limit) : '—'], [__('Per job limit'), $f->per_job_limit ? money($f->per_job_limit) : '—'], [__('Contract discount'), qty($f->discount_percent).'%']] as [$l, $v])
        <div class="col-6 col-md-3"><div class="card kpi"><div class="card-body py-2"><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-5">{{ $v }}</div></div></div></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3"><div class="card-header d-flex">{{ __('Cost per vehicle') }}<form class="ms-auto d-flex gap-1"><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"><button class="btn btn-sm btn-outline-secondary">{{ __('Apply') }}</button></form></div>
            <table class="table table-sm table-erp mb-0"><thead><tr><th>{{ __('Vehicle') }}</th><th>{{ __('Odometer') }}</th><th>{{ __('Jobs') }}</th><th class="text-end">{{ __('Revenue') }}</th><th class="text-end">{{ __('Cost') }}</th></tr></thead>
            <tbody>@forelse($costs as $c)<tr><td><a href="{{ route('vehicles.show', $c->id) }}">{{ $c->plate_number }} {{ $c->plate_code }}</a></td><td>{{ number_format($c->odometer) }}</td><td>{{ $c->jobs }}</td><td class="text-end font-monospace">{{ money($c->revenue, false) }}</td><td class="text-end font-monospace">{{ money($c->cost, false) }}</td></tr>
            @empty<tr><td colspan="5" class="text-body-secondary">{{ __('Assign vehicles to this fleet from the vehicle form.') }}</td></tr>@endforelse</tbody></table></div>
        <div class="card"><div class="card-header">{{ __('Jobs waiting for monthly billing') }}</div><ul class="list-group list-group-flush small">
            @forelse($pendingJobs as $j)<li class="list-group-item d-flex"><a href="{{ route('job-cards.show', $j) }}">{{ $j->number }}</a><span class="ms-2">{{ $j->vehicle->plate() }}</span><span class="ms-auto">{{ $j->completed_at?->format('Y-m-d') }}</span></li>@empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul></div>
    </div>
    <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Approval requests') }}</div><ul class="list-group list-group-flush small">
        @forelse($f->approvals->sortByDesc('id') as $a)<li class="list-group-item"><div class="d-flex"><a href="{{ route('job-cards.show', $a->job_card_id) }}">{{ $a->jobCard?->number }}</a><span class="ms-auto">{{ money($a->amount, false) }} {!! status_badge($a->status) !!}</span></div>
            @if($a->status === 'pending')<input class="form-control form-control-sm mt-1" readonly value="{{ route('public.fleet-approval', $a->token) }}" onclick="this.select()">@endif</li>
        @empty<li class="list-group-item text-body-secondary">—</li>@endforelse
    </ul></div></div>
</div>
@endsection
