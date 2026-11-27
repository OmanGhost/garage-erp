@extends('layouts.app')
@section('title', $o->number)
@section('content')
<x-page-header :title="__('Sales order').' '.$o->number" icon="bi-bag-check" :breadcrumbs="[__('Sales Orders') => route('crud.index', 'sales-orders'), $o->number]" :subtitle="e($o->customer->name).' · '.status_badge($o->status)">
    @if($o->dropShipOrder)
        @can('purchase_orders.view')<a class="btn btn-outline-primary" href="{{ route('purchase-orders.show', $o->dropShipOrder) }}">{{ $o->dropShipOrder->number }}</a>@endcan
    @else
    @can('sales_orders.update')
        @if($o->status === 'draft')<form method="post" action="{{ route('sales-orders.action', [$o, 'confirm']) }}">@csrf <x-idem/><button class="btn btn-primary">{{ __('Confirm') }}</button></form>@endif
        @if($o->status === 'confirmed')<form method="post" action="{{ route('sales-orders.action', [$o, 'invoice']) }}">@csrf <x-idem/><button class="btn btn-success">{{ __('Create invoice') }}</button></form>@endif
        @if(in_array($o->status, ['draft', 'confirmed']))<form method="post" action="{{ route('sales-orders.action', [$o, 'cancel']) }}" data-confirm="{{ __('Cancel order?') }}">@csrf <x-idem/><button class="btn btn-outline-danger">{{ __('Cancel') }}</button></form>@endif
        @if(in_array($o->status, ['draft', 'confirmed']) && $o->items->whereNotNull('part_id')->isNotEmpty())
            @can('purchase_orders.create')
            <form method="post" action="{{ route('sales-orders.drop-ship', $o) }}" class="d-flex gap-2">@csrf <x-idem/>
                <x-lookup name="supplier_id" type="suppliers" :placeholder="__('Supplier')" required/>
                <button class="btn btn-outline-primary">{{ __('Create drop-ship PO') }}</button>
            </form>
            @endcan
        @endif
    @endcan
    @endif
    @if($o->invoice)<a href="{{ route('invoices.show', $o->invoice) }}" class="btn btn-outline-secondary">{{ $o->invoice->displayNumber() }}</a>@endif
</x-page-header>
<div class="card"><div class="table-responsive"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Available') }}</th></tr></thead>
    <tbody>@foreach($o->items as $i)<tr><td>{{ $i->description }}</td><td>{{ qty($i->qty) }}</td><td class="text-end font-monospace">{{ money($i->unit_price, false) }}</td><td class="text-end font-monospace">{{ money($i->discount, false) }}</td><td class="text-end font-monospace">{{ money($i->line_total, false) }}</td>
        <td>@if($i->part)<span class="badge text-bg-{{ (float) $i->part->available($o->warehouse_id) >= (float) $i->qty ? 'success' : 'danger' }}">{{ qty($i->part->available($o->warehouse_id)) }}</span>@endif</td></tr>@endforeach</tbody>
    <tfoot><tr class="fw-bold"><td colspan="4" class="text-end">{{ __('Total incl. VAT') }}</td><td class="text-end font-monospace">{{ money($o->total) }}</td><td></td></tr></tfoot>
</table></div></div>
@endsection
