@extends('layouts.app')
@section('title', __('New stock transfer'))
@section('content')
<x-page-header :title="__('New stock transfer')" icon="bi-truck" :breadcrumbs="[__('Stock Transfers') => route('crud.index', 'stock-transfers'), __('New')]"/>
<form method="post" action="{{ route('inventory.transfers.store') }}" class="card">@csrf <x-idem/>
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label required">{{ __('From warehouse') }}</label><select name="from_warehouse_id" class="form-select" required>@foreach($warehouses as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label required">{{ __('To warehouse') }}</label><select name="to_warehouse_id" class="form-select" required>@foreach($warehouses as $id => $n)<option value="{{ $id }}" @selected($loop->index === 1)>{{ $n }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">{{ __('Date') }}</label><input type="date" name="transfer_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
        <div class="col-12"><x-qty-items/></div>
        <div class="col-12"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-outline-primary">{{ __('Save draft') }}</button> <button class="btn btn-primary" name="send_now" value="1">{{ __('Save & dispatch') }}</button></div>
</form>
@endsection
