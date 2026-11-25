@extends('layouts.app')
@section('title', $quotation ? __('Edit quotation') : __('New quotation'))
@section('content')
<x-page-header :title="$quotation ? __('Edit quotation').' '.$quotation->number.' v'.$quotation->version : __('New quotation')" icon="bi-file-earmark-text"
    :breadcrumbs="[__('Quotations') => route('crud.index', 'quotations'), $quotation ? $quotation->number : __('New')]"
    :subtitle="$jc ? e($jc->number.' · '.$jc->vehicle?->label().' · '.$jc->customer?->name) : __('Sales quotation for spare parts / services')"/>

<form method="post" action="{{ $quotation ? route('quotations.update', $quotation) : route('quotations.store') }}">
    @csrf @if($quotation) @method('put') @endif <x-idem/>
    <div class="card mb-3"><div class="card-body row g-3">
        @if($jc)
            <input type="hidden" name="job_card_id" value="{{ $jc->id }}">
        @elseif(! $quotation)
            <div class="col-md-5"><label class="form-label required">{{ __('Customer') }}</label><x-lookup name="customer_id" type="customers" required/></div>
        @endif
        <div class="col-md-3"><label class="form-label">{{ __('Valid until') }}</label><input type="date" name="valid_until" class="form-control" value="{{ old('valid_until', $quotation?->valid_until?->toDateString() ?? now()->addDays((int) setting('quotation.validity_days', 14))->toDateString()) }}"></div>
        <div class="col-md-4"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control" value="{{ old('notes', $quotation?->notes) }}"></div>
    </div></div>
    <div class="card mb-3">
        <div class="card-header d-flex"><span>{{ __('Items') }}</span><span class="ms-auto small text-body-secondary">{{ __('Use the Option column (A / B ...) to offer alternative repair options; parts can be new or used.') }}</span></div>
        <div class="card-body">
            <x-line-items :rows="$rows" lookup="services" :services="true" price-field="workshop_price" :with-options="true" :vehicle-type="$jc?->vehicle?->vehicle_type"/>
        </div>
    </div>
    <div class="card mb-3"><div class="card-body">
        <label class="form-label">{{ __('Terms & conditions') }}</label>
        <textarea name="terms" class="form-control" rows="3">{{ old('terms', $quotation?->terms ?? setting('invoice.terms')) }}</textarea>
    </div></div>
    <div class="d-flex justify-content-end gap-2"><button class="btn btn-primary btn-lg"><i class="bi bi-check2"></i> {{ __('Save quotation') }}</button></div>
</form>
@endsection
