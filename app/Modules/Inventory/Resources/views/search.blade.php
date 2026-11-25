@extends('layouts.app')
@section('title', __('Advanced Part Search'))
@section('content')
<x-page-header :title="__('Advanced Part Search')" icon="bi-binoculars" :breadcrumbs="[__('Spare Parts Catalog') => route('crud.index', 'parts'), __('Search')]"
    :subtitle="__('Search by name, barcode, SKU, OEM / alternative number, serial, manufacturer, vehicle make/model/year or VIN.')"/>
<form method="get" class="card mb-3"><div class="card-body row g-2">
    <div class="col-md-4"><input type="search" name="q" value="{{ $f['q'] ?? '' }}" class="form-control" placeholder="{{ __('Name / barcode / SKU / OEM / serial') }}" autofocus data-scan-target></div>
    <div class="col-md-2"><select name="brand_id" class="form-select"><option value="">{{ __('Manufacturer') }}</option>@foreach($brands as $id => $n)<option value="{{ $id }}" @selected(($f['brand_id'] ?? '') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="col-md-2"><select name="part_category_id" class="form-select"><option value="">{{ __('Category') }}</option>@foreach($categories as $id => $n)<option value="{{ $id }}" @selected(($f['part_category_id'] ?? '') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="col-md-2"><select name="part_type" class="form-select"><option value="">{{ __('Part type') }}</option>@foreach(\App\Modules\Inventory\Models\Part::TYPES as $t)<option value="{{ $t }}" @selected(($f['part_type'] ?? '') === $t)>{{ status_label($t) }}</option>@endforeach</select></div>
    <div class="col-md-2"><select name="condition_grade" class="form-select"><option value="">{{ __('Grade') }}</option>@foreach(['A', 'B', 'C', 'damaged'] as $g)<option value="{{ $g }}" @selected(($f['condition_grade'] ?? '') === $g)>{{ $g }}</option>@endforeach</select></div>
    <div class="col-md-2"><select name="make_id" id="f_vehicle_make_id" class="form-select"><option value="">{{ __('Vehicle make') }}</option>@foreach($makes as $id => $n)<option value="{{ $id }}" @selected(($f['make_id'] ?? '') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="col-md-2"><x-lookup name="model_id" type="vehicle_models" params="make_id=#f_vehicle_make_id" :placeholder="__('Model')"/></div>
    <div class="col-md-1"><input type="number" name="year" value="{{ $f['year'] ?? '' }}" class="form-control" placeholder="{{ __('Year') }}"></div>
    <div class="col-md-3"><input name="vin" value="{{ $f['vin'] ?? '' }}" class="form-control text-uppercase" placeholder="VIN" maxlength="17"></div>
    <div class="col-md-2"><select name="warehouse_id" class="form-select"><option value="">{{ __('All warehouses') }}</option>@foreach($warehouses as $id => $n)<option value="{{ $id }}" @selected(($f['warehouse_id'] ?? '') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="col-md-1"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="in_stock" value="1" id="ins" @checked(!empty($f['in_stock']))><label class="form-check-label" for="ins">{{ __('In stock') }}</label></div></div>
    <div class="col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
</div></form>
@if($parts)
<div class="card"><div class="table-responsive"><table class="table table-erp table-hover mb-0 align-middle">
    <thead><tr><th>SKU</th><th>{{ __('Part') }}</th><th>{{ __('OEM') }}</th><th>{{ __('Brand') }}</th><th>{{ __('Type') }}</th><th>{{ __('Available') }}</th><th class="text-end">{{ __('Retail') }}</th><th class="text-end">{{ __('Wholesale') }}</th></tr></thead>
    <tbody>@forelse($parts as $p)
        <tr><td><a href="{{ route('parts.show', $p) }}">{{ $p->sku }}</a></td><td>{{ $p->displayName() }}</td><td dir="ltr">{{ $p->oem_number }}</td><td>{{ $p->brand?->name }}</td>
            <td>{{ status_label($p->part_type) }} {{ $p->condition_grade }}</td><td><span class="badge text-bg-{{ (float) $p->on_hand - (float) $p->reserved > 0 ? 'success' : 'danger' }}">{{ qty((float) $p->on_hand - (float) $p->reserved) }}</span></td>
            <td class="text-end font-monospace">{{ money($p->sale_price, false) }}</td><td class="text-end font-monospace">{{ money($p->wholesale_price, false) }}</td></tr>
    @empty<tr><td colspan="8"><div class="empty-state"><i class="bi bi-search"></i>{{ __('No matching parts.') }}</div></td></tr>@endforelse</tbody>
</table></div><div class="card-body">{{ $parts->links() }}</div></div>
@endif
@endsection
