@extends('layouts.app')
@section('title', __('New sales order'))
@section('content')
<x-page-header :title="__('New sales order')" icon="bi-bag-check" :breadcrumbs="[__('Sales Orders') => route('crud.index', 'sales-orders'), __('New')]"/>
<form method="post" action="{{ route('sales-orders.store') }}">@csrf <x-idem/>
    <input type="hidden" name="payment_terms" value="credit"><input type="hidden" name="price_level" value="retail">
    <div class="card mb-3"><div class="card-body row g-3">
        <div class="col-md-5"><label class="form-label required">{{ __('Customer') }}</label><x-lookup name="customer_id" type="customers" required/></div>
        <div class="col-md-2"><label class="form-label">{{ __('Order date') }}</label><input type="date" name="issue_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
        <div class="col-md-2"><label class="form-label">{{ __('Delivery date') }}</label><input type="date" name="due_date" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">{{ __('Warehouse') }}</label><select name="warehouse_id" class="form-select">@foreach($warehouses as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
        <div class="col-12"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
    </div></div>
    <div class="card mb-3"><div class="card-body"><x-line-items lookup="parts" :services="true"/></div></div>
    <div class="text-end"><button class="btn btn-primary btn-lg">{{ __('Save order') }}</button></div>
</form>
@endsection
