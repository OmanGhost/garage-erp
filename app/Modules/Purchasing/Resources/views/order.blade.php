@extends('layouts.app')
@section('title', $po->number)
@section('content')
<x-page-header :title="__('Purchase order').' '.$po->number" icon="bi-cart-check" :breadcrumbs="[__('Purchase Orders') => route('crud.index', 'purchase-orders'), $po->number]"
    :subtitle="'<a href=\''.route('suppliers.show', $po->supplier).'\'>'.e($po->supplier->name).'</a> · '.e($po->warehouse->displayName()).' · '.status_badge($po->status)">
    <a href="{{ route('purchase-orders.print', $po) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print') }}</a>
    @if($po->status === 'draft')@can('purchase_orders.update')<form method="post" action="{{ route('purchase-orders.action', [$po, 'submit']) }}">@csrf <x-idem/><button class="btn btn-outline-primary">{{ __('Submit for approval') }}</button></form>@endcan @endif
    @if(in_array($po->status, ['draft', 'pending_approval']))@can('purchase_orders.approve')<form method="post" action="{{ route('purchase-orders.action', [$po, 'approve']) }}">@csrf <x-idem/><button class="btn btn-success"><i class="bi bi-check2"></i> {{ __('Approve') }}</button></form>@endcan @endif
    @if(in_array($po->status, ['approved', 'partially_received']))@can('goods_receipts.create')<a href="{{ route('purchase-orders.receive', $po) }}" class="btn btn-primary"><i class="bi bi-box-seam"></i> {{ __('Receive goods') }}</a>@endcan @endif
    @if($po->status === 'partially_received')@can('purchase_orders.update')<form method="post" action="{{ route('purchase-orders.action', [$po, 'close']) }}" data-confirm="{{ __('Close the order (no more receipts)?') }}">@csrf <x-idem/><button class="btn btn-outline-secondary">{{ __('Close') }}</button></form>@endcan @endif
    @if(in_array($po->status, ['draft', 'pending_approval', 'approved']) && $po->receipts->isEmpty())@can('purchase_orders.delete')<form method="post" action="{{ route('purchase-orders.action', [$po, 'cancel']) }}" data-confirm="{{ __('Cancel this order?') }}">@csrf <x-idem/><button class="btn btn-outline-danger">{{ __('Cancel') }}</button></form>@endcan @endif
</x-page-header>
<div class="row g-3">
    <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-erp mb-0">
        <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Ordered') }}</th><th>{{ __('Received') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('VAT') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
        <tbody>@foreach($po->items as $i)<tr><td>{{ $i->part->label() }}</td><td>{{ qty($i->qty) }}</td><td class="{{ (float) $i->received_qty >= (float) $i->qty ? 'text-success' : 'text-warning' }}">{{ qty($i->received_qty) }}</td>
            <td class="text-end font-monospace">{{ money($i->unit_cost, false) }}</td><td class="text-end font-monospace">{{ money($i->discount, false) }}</td><td class="text-end font-monospace">{{ money($i->tax_amount, false) }}</td><td class="text-end font-monospace">{{ money($i->line_total, false) }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="6" class="text-end">{{ __('Subtotal') }}</td><td class="text-end font-monospace">{{ money((float) $po->subtotal - (float) $po->discount_total, false) }}</td></tr>
            <tr><td colspan="6" class="text-end">{{ __('VAT') }}</td><td class="text-end font-monospace">{{ money($po->tax_total, false) }}</td></tr>
            <tr class="fw-bold"><td colspan="6" class="text-end">{{ __('Total') }}</td><td class="text-end font-monospace">{{ money($po->total) }}</td></tr></tfoot>
    </table></div></div></div>
    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-body small">
            <div><b>{{ __('Order date') }}:</b> {{ $po->order_date->format('Y-m-d') }} · <b>{{ __('Expected') }}:</b> {{ $po->expected_date?->format('Y-m-d') ?? '—' }}</div>
            <div><b>{{ __('Created by') }}:</b> {{ $po->creator?->name }}</div>
            @if($po->approver)<div><b>{{ __('Approved by') }}:</b> {{ $po->approver->name }} · {{ $po->approved_at?->format('Y-m-d H:i') }}</div>@endif
            @if($po->notes)<div class="mt-1">{{ $po->notes }}</div>@endif
        </div></div>
        <div class="card mb-3"><div class="card-header">{{ __('Goods receipts') }}</div><ul class="list-group list-group-flush small">
            @forelse($po->receipts as $g)<li class="list-group-item d-flex"><a href="{{ route('goods-receipts.show', $g) }}">{{ $g->number }}</a><span class="ms-2">{{ $g->received_at->format('Y-m-d') }}</span><span class="ms-auto font-monospace">{{ money($g->total, false) }}</span></li>@empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul></div>
        <div class="card"><div class="card-header">{{ __('Supplier invoices') }}</div><ul class="list-group list-group-flush small">
            @forelse($po->invoices as $i)<li class="list-group-item d-flex"><a href="{{ route('supplier-invoices.show', $i) }}">{{ $i->number }}</a><span class="ms-auto me-2 font-monospace">{{ money($i->total, false) }}</span>{!! status_badge($i->status) !!}</li>@empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul></div>
    </div>
</div>
@endsection
