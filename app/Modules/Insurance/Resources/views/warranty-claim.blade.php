@extends('layouts.app')
@section('title', $c->number)
@section('content')
<x-page-header :title="__('Warranty claim').' '.$c->number" icon="bi-patch-exclamation" :breadcrumbs="[__('Warranty Claims') => route('crud.index', 'warranty-claims'), $c->number]" :subtitle="status_badge($c->status)"/>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-body small">
            <div class="fw-semibold">{{ $c->warranty->description }}</div>
            <div>{{ $c->warranty->customer->name }} · {{ $c->warranty->vehicle?->label() }}</div>
            <div>{{ __('Warranty') }}: {{ $c->warranty->start_date->format('Y-m-d') }} → {{ $c->warranty->end_date->format('Y-m-d') }}</div>
            <hr><div><b>{{ __('Claim date') }}:</b> {{ $c->claim_date->format('Y-m-d') }} · {{ __('Odometer') }} {{ number_format($c->odometer) }}</div>
            <div>{{ $c->description }}</div>
            @if($c->inspection_result)<div class="mt-2"><b>{{ __('Inspection') }}:</b> {{ $c->inspection_result }}</div>@endif
            @if($c->reworkJobCard)<div class="mt-2"><b>{{ __('Rework job') }}:</b> <a href="{{ route('job-cards.show', $c->reworkJobCard) }}">{{ $c->reworkJobCard->number }}</a> ({{ status_label($c->reworkJobCard->status) }})</div>@endif
            <div class="mt-2"><b>{{ __('Warranty cost') }}:</b> {{ money($c->cost) }} · <b>{{ __('Recovered from supplier') }}:</b> {{ money($c->supplier_recovered_amount) }}</div>
        </div></div>
        <x-attachments :model="$c" :title="__('Photos of returned part / reports')"/>
    </div>
    <div class="col-lg-6">
        @can('warranty_claims.approve')
        @if(in_array($c->status, ['open', 'inspecting']))
        <form method="post" action="{{ route('warranty.claims.action', [$c, 'approve']) }}" class="card mb-3">@csrf <x-idem/><div class="card-header">{{ __('Inspect & decide') }}</div><div class="card-body">
            <textarea name="inspection_result" class="form-control mb-2" rows="2" placeholder="{{ __('Inspection result of the returned part') }}"></textarea>
            <select name="resolution" class="form-select mb-2"><option value="replace">{{ __('Replace part') }}</option><option value="repair">{{ __('Repair again') }}</option><option value="refund">{{ __('Refund') }}</option></select>
            <button class="btn btn-success w-100">{{ __('Approve & create rework job (free)') }}</button>
        </div></form>
        <form method="post" action="{{ route('warranty.claims.action', [$c, 'reject']) }}" class="card mb-3" data-confirm="{{ __('Reject the claim?') }}">@csrf <x-idem/><div class="card-body">
            <input name="inspection_result" class="form-control mb-2" placeholder="{{ __('Reason') }}" required><button class="btn btn-outline-danger w-100">{{ __('Reject claim') }}</button></div></form>
        @endif
        @if($c->status === 'approved')<form method="post" action="{{ route('warranty.claims.action', [$c, 'complete']) }}" class="mb-3">@csrf <x-idem/><button class="btn btn-primary w-100">{{ __('Complete claim (record rework cost)') }}</button></form>@endif
        <form method="post" action="{{ route('warranty.claims.action', [$c, 'supplier']) }}" class="card">@csrf <x-idem/><div class="card-header">{{ __('Supplier warranty claim') }} — {{ $c->warranty->supplier?->name ?? '—' }}</div><div class="card-body row g-2">
            <div class="col-6"><select name="supplier_claim_status" class="form-select">@foreach(['submitted', 'accepted', 'rejected', 'settled'] as $s)<option value="{{ $s }}" @selected($c->supplier_claim_status === $s)>{{ status_label($s) }}</option>@endforeach</select></div>
            <div class="col-6"><input type="number" step="0.001" min="0" name="supplier_recovered_amount" value="{{ (float) $c->supplier_recovered_amount }}" class="form-control"></div>
            <div class="col-12"><button class="btn btn-outline-primary w-100">{{ __('Save') }}</button></div></div></form>
        @endcan
    </div>
</div>
@endsection
