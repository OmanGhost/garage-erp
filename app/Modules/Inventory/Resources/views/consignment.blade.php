@extends('layouts.app')
@section('title', __('Consignment stock'))
@section('content')
<x-page-header :title="__('Consignment stock')" icon="bi-boxes"/>
<div class="card mb-3"><div class="table-responsive"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('Warehouse') }}</th><th>{{ __('Consignor') }}</th><th>{{ __('Stock value') }}</th><th>{{ __('Unsettled sales at cost') }}</th><th>{{ __('Last settlement') }}</th></tr></thead>
    <tbody>@forelse($warehouses as $row)
        <tr><td><a href="{{ route('inventory.consignment.show', $row['w']) }}">{{ $row['w']->displayName() }}</a></td><td>{{ $row['w']->consignor?->name }}</td><td>{{ money($row['value']) }}</td><td>{{ money($row['sold']) }}</td><td>{{ $row['last']?->toDateString() }}</td></tr>
    @empty<tr><td colspan="5">{{ __('No records') }}</td></tr>@endforelse</tbody>
</table></div></div>
@endsection
