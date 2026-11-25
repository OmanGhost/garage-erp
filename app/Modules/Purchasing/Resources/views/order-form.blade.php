@extends('layouts.app')
@section('title', $po ? __('Edit purchase order') : __('New purchase order'))
@section('content')
<x-page-header :title="$po ? __('Edit purchase order').' '.$po->number : __('New purchase order')" icon="bi-cart-check" :breadcrumbs="[__('Purchase Orders') => route('crud.index', 'purchase-orders'), $po ? $po->number : __('New')]"/>
<form method="post" action="{{ $po ? route('purchase-orders.update', $po) : route('purchase-orders.store') }}">@csrf @if($po) @method('put') @endif <x-idem/>
    <div class="card mb-3"><div class="card-body row g-3">
        <div class="col-md-5"><label class="form-label required">{{ __('Supplier') }}</label><x-lookup name="supplier_id" type="suppliers" :value="$po?->supplier_id" :text="$po?->supplier?->name ?? ''" required/></div>
        <div class="col-md-3"><label class="form-label">{{ __('Receiving warehouse') }}</label><select name="warehouse_id" class="form-select">@foreach($warehouses as $id => $n)<option value="{{ $id }}" @selected($po?->warehouse_id === $id)>{{ $n }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">{{ __('Expected date') }}</label><input type="date" name="expected_date" class="form-control" value="{{ $po?->expected_date?->toDateString() }}"></div>
        <div class="col-md-12"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control" value="{{ $po?->notes }}"></div>
    </div></div>
    <div class="card mb-3"><div class="card-body"><x-line-items :rows="$rows" lookup="parts" price-field="purchase_price" :cost-label="__('Unit cost')"/></div></div>
    <div class="card mb-3"><div class="card-body"><label class="form-label">{{ __('Terms') }}</label><textarea name="terms" class="form-control" rows="2">{{ $po?->terms }}</textarea></div></div>
    <div class="text-end"><button class="btn btn-outline-primary btn-lg">{{ __('Save draft') }}</button> @unless($po)<button class="btn btn-primary btn-lg" name="submit" value="1">{{ __('Save & submit for approval') }}</button>@endunless</div>
</form>
@endsection
