@extends('layouts.app')
@section('title', $invoice ? __('Edit invoice') : __('New invoice'))
@section('content')
<x-page-header :title="$invoice ? __('Edit invoice').' '.$invoice->displayNumber() : __('New sales invoice')" icon="bi-receipt" :breadcrumbs="[__('Invoices') => route('crud.index', 'invoices'), $invoice ? $invoice->displayNumber() : __('New')]"/>
<form method="post" action="{{ $invoice ? route('invoices.update', $invoice) : route('invoices.store') }}" x-data="{ level: @js(old('price_level', $invoice?->price_level ?? $customer?->price_level ?? 'retail')) }">
    @csrf @if($invoice) @method('put') @endif <x-idem/>
    <div class="card mb-3"><div class="card-body row g-3">
        <div class="col-md-4" @lookup-selected="level = $event.detail.extra?.price_level || 'retail'"><label class="form-label required">{{ __('Customer') }}</label>
            <x-lookup name="customer_id" type="customers" :value="$invoice?->customer_id ?? $customer?->id" :text="$invoice?->customer?->name ?? $customer?->name ?? ''" required/></div>
        <div class="col-md-2"><label class="form-label">{{ __('Date') }}</label><input type="date" name="issue_date" class="form-control" value="{{ old('issue_date', $invoice?->issue_date?->toDateString() ?? now()->toDateString()) }}" required></div>
        <div class="col-md-2"><label class="form-label">{{ __('Terms') }}</label><select name="payment_terms" class="form-select"><option value="cash" @selected(($invoice?->payment_terms ?? 'cash') === 'cash')>{{ __('Cash') }}</option><option value="credit" @selected($invoice?->payment_terms === 'credit')>{{ __('Credit') }}</option></select></div>
        <div class="col-md-2"><label class="form-label">{{ __('Due date') }}</label><input type="date" name="due_date" class="form-control" value="{{ old('due_date', $invoice?->due_date?->toDateString()) }}"></div>
        <div class="col-md-2"><label class="form-label">{{ __('Price level') }}</label><select name="price_level" class="form-select" x-model="level"><option value="retail">{{ __('Retail') }}</option><option value="wholesale">{{ __('Wholesale') }}</option><option value="workshop">{{ __('Workshop') }}</option></select></div>
        <div class="col-md-3"><label class="form-label">{{ __('Warehouse') }}</label><select name="warehouse_id" class="form-select" required>@foreach($warehouses as $id => $n)<option value="{{ $id }}" @selected($invoice?->warehouse_id === $id)>{{ $n }}</option>@endforeach</select></div>
        <div class="col-md-9"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control" value="{{ old('notes', $invoice?->notes) }}"></div>
    </div></div>
    @if($invoice?->job_card_id)
        <div class="alert alert-info">{{ __('Lines of workshop invoices come from the job card and cannot be edited here.') }}</div>
        @foreach($rows as $i => $r)@foreach($r as $k => $v)<input type="hidden" name="items[{{ $i }}][{{ $k }}]" value="{{ $v }}">@endforeach @endforeach
    @else
        <div class="card mb-3"><div class="card-body"><x-line-items :rows="$rows" lookup="parts" :services="true" price-field="sale_price"/></div></div>
    @endif
    <div class="d-flex justify-content-end gap-2">
        <button class="btn btn-outline-primary btn-lg"><i class="bi bi-save"></i> {{ __('Save draft') }}</button>
        @can('invoices.post')@unless($invoice)<button class="btn btn-success btn-lg" name="post_now" value="1" data-confirm="{{ __('Post the invoice now? Stock and accounts will be updated.') }}"><i class="bi bi-check2-circle"></i> {{ __('Save & post') }}</button>@endunless @endcan
    </div>
</form>
@endsection
