@extends('layouts.app')
@section('title', __('Warranty claim'))
@section('content')
<x-page-header :title="__('Open warranty claim')" icon="bi-patch-exclamation" :breadcrumbs="[__('Warranties') => route('crud.index', 'warranties'), __('Claim')]"/>
<div class="row g-3">
    <div class="col-lg-5"><div class="card"><div class="card-body small">
        <div class="fw-semibold">{{ $w->description }}</div>
        <div>{{ __('Customer') }}: {{ $w->customer->name }} · {{ __('Vehicle') }}: {{ $w->vehicle?->label() }}</div>
        <div>{{ __('Invoice') }}: {{ $w->invoice?->number }} · {{ $w->start_date->format('Y-m-d') }} → <b>{{ $w->end_date->format('Y-m-d') }}</b></div>
        @if($w->km_limit)<div>{{ __('Km limit') }}: {{ number_format($w->km_limit) }} {{ __('from') }} {{ number_format($w->start_odometer) }}</div>@endif
        @if($w->serial_number)<div>{{ __('Serial') }}: {{ $w->serial_number }}</div>@endif
    </div></div></div>
    <div class="col-lg-7"><form method="post" action="{{ route('warranty.claims.store') }}" class="card">@csrf <x-idem/>
        <input type="hidden" name="warranty_id" value="{{ $w->id }}">
        <div class="card-body row g-2">
            <div class="col-md-4"><label class="form-label">{{ __('Current odometer') }}</label><input type="number" name="odometer" min="0" class="form-control"></div>
            <div class="col-12"><label class="form-label required">{{ __('Problem description') }}</label><textarea name="description" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="supplier_claim" value="1" id="sc"><label class="form-check-label" for="sc">{{ __('Claim from supplier warranty as well') }}</label></div></div>
        </div>
        <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Open claim') }}</button></div>
    </form></div>
</div>
@endsection
