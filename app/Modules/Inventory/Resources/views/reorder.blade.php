@extends('layouts.app')
@section('title', __('Reorder Suggestions'))
@section('content')
<x-page-header :title="__('Reorder suggestions & demand forecast')" icon="bi-graph-up-arrow" :breadcrumbs="[__('Inventory') => route('crud.index', 'stock-levels'), __('Reorder')]"
    :subtitle="__('Parts at or below minimum stock, or whose forecast demand (exponential smoothing of the last 6 months) exceeds available stock.')">
    <form method="get" class="d-flex gap-2"><select name="warehouse_id" class="form-select" onchange="this.form.submit()"><option value="">{{ __('All warehouses') }}</option>@foreach($warehouses as $id => $n)<option value="{{ $id }}" @selected(request('warehouse_id') == $id)>{{ $n }}</option>@endforeach</select></form>
</x-page-header>
<form method="post" action="{{ route('inventory.reorder.order') }}" class="card mb-4">@csrf <x-idem/>
    <div class="table-responsive"><table class="table table-erp mb-0 align-middle">
        <thead><tr><th>SKU</th><th>{{ __('Part') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Available') }}</th><th>{{ __('Minimum') }}</th><th>{{ __('Last 6 months') }}</th><th>{{ __('Forecast') }}</th><th style="width:120px">{{ __('Order qty') }}</th><th class="text-end">{{ __('Est. cost') }}</th></tr></thead>
        <tbody>@forelse($rows as $r)
            <tr><td><a href="{{ route('parts.show', $r['part']) }}">{{ $r['part']->sku }}</a></td><td>{{ $r['part']->displayName() }}</td><td class="small">{{ $r['part']->supplier?->name ?? '—' }}</td>
                <td class="text-danger fw-bold">{{ qty($r['available']) }}</td><td>{{ qty($r['min_stock']) }}</td><td class="small font-monospace">{{ implode(' · ', array_map(fn($v) => qty($v), $r['history'])) }}</td>
                <td>{{ qty($r['forecast']) }}</td><td><input type="number" step="1" min="0" name="qty[{{ $r['part']->id }}]" value="{{ $r['suggested_qty'] }}" class="form-control form-control-sm"></td>
                <td class="text-end font-monospace">{{ money($r['estimated_cost'], false) }}</td></tr>
        @empty<tr><td colspan="9"><div class="empty-state"><i class="bi bi-check2-circle"></i>{{ __('No reorder needed.') }}</div></td></tr>@endforelse</tbody>
    </table></div>
    @if($rows)
    <div class="card-footer d-flex gap-2 justify-content-end align-items-center">
        <span class="small text-body-secondary">{{ __('Draft purchase orders are created per default supplier.') }}</span>
        <select name="warehouse_id" class="form-select w-auto">@foreach($warehouses as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
        @can('purchase_orders.create')<button class="btn btn-primary"><i class="bi bi-cart-plus"></i> {{ __('Create purchase orders') }}</button>@endcan
    </div>
    @endif
</form>
<div class="card"><div class="card-header d-flex"><span><i class="bi bi-hourglass-bottom me-1"></i>{{ __('Slow-moving / dead stock') }}</span>
    <form method="get" class="ms-auto d-flex gap-1 align-items-center small">{{ __('No sales for') }} <input type="number" name="days" value="{{ request('days', 180) }}" class="form-control form-control-sm" style="width:80px"> {{ __('days') }} <button class="btn btn-sm btn-outline-secondary">{{ __('Apply') }}</button></form></div>
    <div class="table-responsive"><table class="table table-sm table-erp mb-0">
        <thead><tr><th>SKU</th><th>{{ __('Part') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Qty') }}</th><th class="text-end">{{ __('Value') }}</th><th>{{ __('Last movement') }}</th></tr></thead>
        <tbody>@forelse($slow as $s)<tr><td>{{ $s->sku }}</td><td>{{ app()->getLocale() === 'en' && $s->name_en ? $s->name_en : $s->name_ar }}</td><td>{{ $s->warehouse }}</td><td>{{ qty($s->qty_on_hand) }}</td><td class="text-end font-monospace">{{ money($s->value, false) }}</td><td>{{ $s->last_movement_at ? substr($s->last_movement_at, 0, 10) : '—' }}</td></tr>
        @empty<tr><td colspan="6" class="text-body-secondary">—</td></tr>@endforelse</tbody>
    </table></div>
</div>
@endsection
