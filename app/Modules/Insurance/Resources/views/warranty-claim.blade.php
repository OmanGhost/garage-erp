@extends('layouts.app')
@section('title', $c->number)
@section('content')
@php
    $w = $c->warranty;
    $flow = ['open' => __('Opened'), 'inspecting' => __('Inspecting'), 'approved' => __('Approved — rework'), 'completed' => __('Completed')];
    $order = array_keys($flow);
    $pos = array_search($c->status, $order, true);
    $evIcon = ['opened' => 'bi-flag-fill text-danger', 'status' => 'bi-arrow-repeat text-primary', 'supplier' => 'bi-truck text-info', 'note' => 'bi-chat-left-text text-secondary'];
@endphp
<x-page-header :title="__('Warranty claim').' '.$c->number" icon="bi-patch-exclamation" :breadcrumbs="[__('Warranty Claims') => route('crud.index', 'warranty-claims'), $c->number]" :subtitle="e($w->description).' · '.e($w->customer->name).' · '.status_badge($c->status)">
    @can('warranty_claims.print')
        <a class="btn btn-outline-secondary" href="{{ route('warranty.claims.print', $c) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Print claim') }}</a>
    @endcan
    @can('warranties.print')
        <a class="btn btn-outline-secondary" href="{{ route('warranties.print', $w) }}" target="_blank" rel="noopener"><i class="bi bi-patch-check"></i> {{ __('Warranty card') }}</a>
    @endcan
</x-page-header>

{{-- progress --}}
<div class="card mb-3"><div class="card-body">
    @if($c->status === 'rejected')
        <div class="d-flex gap-2 align-items-center text-danger"><i class="bi bi-x-octagon fs-4"></i><div><div class="fw-semibold">{{ __('Claim rejected') }}</div><div class="small">{{ $c->inspection_result }}</div></div></div>
    @else
        <div class="d-flex flex-wrap gap-4">
            @foreach($flow as $k => $stepLabel)
                @php $done = $pos !== false && array_search($k, $order, true) <= $pos; @endphp
                <div class="d-flex gap-2 align-items-center"><i class="bi {{ $done ? 'bi-check-circle-fill text-success' : 'bi-circle text-body-tertiary' }} fs-5"></i><span class="{{ $done ? 'fw-semibold' : 'text-body-secondary' }}">{{ $stepLabel }}</span></div>
            @endforeach
        </div>
    @endif
</div></div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-card-text"></i> {{ __('Claim details') }}</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-body-secondary">{{ __('Claim date') }}</dt><dd class="col-sm-8">{{ $c->claim_date->format('Y-m-d') }} · {{ __('Odometer') }} {{ $c->odometer ? number_format($c->odometer).' km' : '—' }}</dd>
                    <dt class="col-sm-4 text-body-secondary">{{ __('Problem reported') }}</dt><dd class="col-sm-8" style="white-space:pre-line">{{ $c->description }}</dd>
                    <dt class="col-sm-4 text-body-secondary">{{ __('Inspection') }}</dt><dd class="col-sm-8" style="white-space:pre-line">{{ $c->inspection_result ?: '—' }}</dd>
                    <dt class="col-sm-4 text-body-secondary">{{ __('Resolution') }}</dt><dd class="col-sm-8">{{ $c->resolution ? status_label($c->resolution) : '—' }}</dd>
                    <dt class="col-sm-4 text-body-secondary">{{ __('Rework job') }}</dt><dd class="col-sm-8">@if($c->reworkJobCard)<a href="{{ route('job-cards.show', $c->reworkJobCard) }}">{{ $c->reworkJobCard->number }}</a> ({{ status_label($c->reworkJobCard->status) }})@else — @endif</dd>
                    <dt class="col-sm-4 text-body-secondary">{{ __('Warranty cost') }}</dt><dd class="col-sm-8 font-monospace">{{ money($c->cost) }} · {{ __('Recovered from supplier') }} {{ money($c->supplier_recovered_amount) }}</dd>
                    <dt class="col-sm-4 text-body-secondary">{{ __('Opened by') }}</dt><dd class="col-sm-8">{{ $c->creator?->name ?? '—' }}</dd>
                </dl>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-patch-check"></i> {{ __('Warranty') }}</div>
            <div class="card-body small">
                <div class="fw-semibold">{{ $w->description }}@if($w->part) · <span dir="ltr">{{ $w->part->sku }}</span>@endif</div>
                <div>{{ $w->customer->name }} · {{ $w->vehicle?->label() }}</div>
                <div><span dir="ltr">{{ $w->start_date->format('Y-m-d') }} → {{ $w->end_date->format('Y-m-d') }}</span> @if($w->km_limit)· {{ number_format($w->km_limit) }} km @endif · {!! status_badge($w->status) !!}</div>
                @if($w->invoice)<div>{{ __('Invoice') }}: <a href="{{ route('invoices.show', $w->invoice) }}">{{ $w->invoice->displayNumber() }}</a></div>@endif
            </div>
        </div>
        <x-attachments :model="$c" :title="__('Photos of returned part / reports')"/>
    </div>
    <div class="col-xl-5">
        @can('warranty_claims.approve')
        @if(in_array($c->status, ['open', 'inspecting']))
        <form method="post" action="{{ route('warranty.claims.action', [$c, 'approve']) }}" class="card mb-3">@csrf <x-idem/><div class="card-header">{{ __('Inspect & decide') }}</div><div class="card-body">
            <textarea name="inspection_result" class="form-control mb-2" rows="2" placeholder="{{ __('Inspection result of the returned part') }}" aria-label="{{ __('Inspection') }}"></textarea>
            <select name="resolution" class="form-select mb-2" aria-label="{{ __('Resolution') }}"><option value="replace">{{ __('Replace part') }}</option><option value="repair">{{ __('Repair again') }}</option><option value="refund">{{ __('Refund') }}</option></select>
            <button class="btn btn-success w-100">{{ __('Approve & create rework job (free)') }}</button>
        </div></form>
        <form method="post" action="{{ route('warranty.claims.action', [$c, 'reject']) }}" class="card mb-3" data-confirm="{{ __('Reject the claim?') }}">@csrf <x-idem/><div class="card-body">
            <input name="inspection_result" class="form-control mb-2" placeholder="{{ __('Reason') }}" required aria-label="{{ __('Reason') }}"><button class="btn btn-outline-danger w-100">{{ __('Reject claim') }}</button></div></form>
        @endif
        @if($c->status === 'approved')<form method="post" action="{{ route('warranty.claims.action', [$c, 'complete']) }}" class="mb-3">@csrf <x-idem/><button class="btn btn-primary w-100">{{ __('Complete claim (record rework cost)') }}</button></form>@endif
        <form method="post" action="{{ route('warranty.claims.action', [$c, 'supplier']) }}" class="card mb-3">@csrf <x-idem/><div class="card-header">{{ __('Supplier warranty claim') }} — {{ $w->supplier?->name ?? '—' }}</div><div class="card-body row g-2">
            <div class="col-6"><select name="supplier_claim_status" class="form-select" aria-label="{{ __('Status') }}">@foreach(['submitted', 'accepted', 'rejected', 'settled'] as $s)<option value="{{ $s }}" @selected($c->supplier_claim_status === $s)>{{ status_label($s) }}</option>@endforeach</select></div>
            <div class="col-6"><input type="number" step="0.001" min="0" name="supplier_recovered_amount" value="{{ (float) $c->supplier_recovered_amount }}" class="form-control" aria-label="{{ __('Recovered from supplier') }}"></div>
            <div class="col-12"><button class="btn btn-outline-primary w-100">{{ __('Save') }}</button></div></div></form>
        @endcan

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clock-history"></i> {{ __('Tracking history') }}</div>
            <ul class="list-group list-group-flush small">
                @forelse($c->events as $e)
                    <li class="list-group-item d-flex gap-2"><i class="bi {{ $evIcon[$e->event] ?? 'bi-dot' }}"></i>
                        <div class="flex-grow-1"><div class="fw-semibold">{{ $label($e) }}</div>
                            @if($e->notes)<div style="white-space:pre-line">{{ $e->notes }}</div>@endif
                            <div class="text-body-secondary">{{ $e->created_at?->format('Y-m-d H:i') }} · {{ $e->user?->name ?? __('System') }}</div></div></li>
                @empty
                    <li class="list-group-item text-body-secondary">—</li>
                @endforelse
            </ul>
            @can('warranty_claims.update')
            <div class="card-footer"><form method="post" action="{{ route('warranty.claims.note', $c) }}" class="d-flex gap-2">@csrf
                <input name="note" class="form-control form-control-sm" required maxlength="1000" placeholder="{{ __('Add a note…') }}" aria-label="{{ __('Add a note…') }}"><button class="btn btn-sm btn-outline-primary">{{ __('Add') }}</button></form></div>
            @endcan
        </div>
    </div>
</div>
@endsection
