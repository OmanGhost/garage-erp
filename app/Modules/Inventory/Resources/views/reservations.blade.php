@extends('layouts.app')
@section('title', __('Stock reservations'))
@section('content')
<x-page-header :title="__('Stock reservations')" icon="bi-bookmark"/>
@can('stock.update')
<form method="post" action="{{ route('inventory.reservations.store') }}" class="card card-body mb-3">@csrf <x-idem/>
    <div class="d-flex flex-wrap gap-3 align-items-end">
        <label>{{ __('Part') }}<x-lookup name="part_id" type="parts" required/></label>
        <label>{{ __('Warehouse') }}<select name="warehouse_id" class="form-select" required>@foreach($warehouses as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
        <label>{{ __('Qty') }}<input class="form-control" name="qty" type="number" step=".001" min=".001" required></label>
        <label>{{ __('Expires') }}<input class="form-control" name="expires_at" type="date" value="{{ now()->addDays(7)->toDateString() }}"></label>
        <label>{{ __('Customer') }}<x-lookup name="customer_id" type="customers"/></label>
        <label>{{ __('Job card') }}<x-lookup name="job_card_id" type="job_cards"/></label>
        <label>{{ __('Sales order') }}<x-lookup name="sales_order_id" type="sales_orders"/></label>
        <label>{{ __('Reason') }}<input class="form-control" name="reason" maxlength="190"></label>
        <button class="btn btn-primary">{{ __('Reserve stock') }}</button>
    </div>
</form>
@endcan
<nav class="nav nav-pills mb-3">
    @foreach(['active', 'released', 'consumed', 'expired'] as $key)
        <a class="nav-link {{ $key === $status ? 'active' : '' }}" href="{{ request()->url() }}?status={{ $key }}">{{ status_label($key) }}</a>
    @endforeach
</nav>
<div class="card mb-3"><div class="table-responsive"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Part') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Reserved for') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Actions') }}</th></tr></thead>
    <tbody>@forelse($rows as $r)
        <tr><td>{{ $r->number }}</td><td>{{ $r->part?->label() }}</td><td>{{ $r->warehouse?->displayName() }}</td><td>{{ qty($r->qty) }}</td>
            <td>{{ collect([$r->customer?->name, $r->jobCard?->number, $r->salesOrder?->number, $r->reason])->filter()->join(' ? ') }}</td><td>{{ $r->expires_at?->toDateString() }}</td>
            <td>@if($r->status === 'active') @can('stock.update')
                <form method="post" action="{{ route('inventory.reservations.release', $r) }}">@csrf <x-idem/>
                    <button class="btn btn-outline-primary btn-sm">{{ __('Release') }}</button>
                    <button name="as" value="consumed" class="btn btn-outline-secondary btn-sm">{{ __('Mark consumed') }}</button>
                </form>
            @endcan @endif</td></tr>
    @empty<tr><td colspan="7">{{ __('No records') }}</td></tr>@endforelse</tbody>
</table></div></div>
{{ $rows->links() }}
@endsection
