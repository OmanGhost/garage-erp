@extends('layouts.app')
@section('title', __('New stock count'))
@section('content')
<x-page-header :title="__('New stock count')" icon="bi-clipboard-data" :breadcrumbs="[__('Stock Counts') => route('crud.index', 'stock-counts'), __('New')]"/>
<form method="post" action="{{ route('inventory.counts.store') }}" class="card" style="max-width:720px">@csrf <x-idem/>
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label">{{ __('Warehouse') }}</label><select name="warehouse_id" class="form-select">@foreach($warehouses as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">{{ __('Type') }}</label><select name="type" class="form-select"><option value="periodic">{{ __('Periodic') }}</option><option value="annual">{{ __('Annual') }}</option><option value="cycle">{{ __('Cycle count') }}</option></select></div>
        <div class="col-md-6"><label class="form-label">{{ __('Only category (optional)') }}</label><select name="part_category_id" class="form-select"><option value="">{{ __('All') }}</option>@foreach($categories as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
        <div class="col-12 small text-body-secondary">{{ __('The system quantity is frozen when the count is created. Enter the physical quantities, then post to adjust stock.') }}</div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Start count') }}</button></div>
</form>
@endsection
