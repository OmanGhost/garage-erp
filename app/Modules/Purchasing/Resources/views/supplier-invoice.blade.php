@extends('layouts.app')
@section('title', $si->number)
@section('content')
<x-page-header :title="__('Supplier invoice').' '.$si->number" icon="bi-receipt" :breadcrumbs="[__('Supplier Invoices') => route('crud.index', 'supplier-invoices'), $si->number]"
    :subtitle="e($si->supplier->name).' · '.__('Ref').' '.e($si->supplier_invoice_number).' · '.status_badge($si->status)">
    @if($si->status === 'draft')@can('supplier_invoices.approve')<form method="post" action="{{ route('supplier-invoices.post', $si) }}" data-confirm="{{ __('Post this supplier invoice?') }}">@csrf <x-idem/><button class="btn btn-success">{{ __('Post') }}</button></form>@endcan @endif
    @if(in_array($si->status, ['posted', 'partially_paid']))@can('payments.create')<a href="{{ route('payments.pay', ['supplier_id' => $si->supplier_id]) }}" class="btn btn-danger">{{ __('Pay') }}</a>@endcan @endif
</x-page-header>
<div class="row g-3">
    <div class="col-xl-8"><div class="card"><table class="table table-erp mb-0">
        <thead><tr><th>{{ __('Description') }}</th><th>{{ __('Account') }}</th><th>{{ __('Qty') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end">{{ __('VAT') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
        <tbody>@foreach($si->items as $i)<tr><td>{{ $i->description }}</td><td class="small">{{ $i->account?->label() ?? ($i->part ? __('Inventory (via GRN)') : '') }}</td><td>{{ qty($i->qty) }}</td><td class="text-end font-monospace">{{ money($i->unit_cost, false) }}</td><td class="text-end font-monospace">{{ money($i->tax_amount, false) }}</td><td class="text-end font-monospace">{{ money($i->line_total, false) }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="5" class="text-end">{{ __('VAT') }}</td><td class="text-end font-monospace">{{ money($si->tax_total, false) }}</td></tr><tr class="fw-bold"><td colspan="5" class="text-end">{{ __('Total') }}</td><td class="text-end font-monospace">{{ money($si->total) }}</td></tr></tfoot>
    </table></div></div>
    <div class="col-xl-4"><div class="card"><div class="card-body small">
        <div><b>{{ __('Date') }}:</b> {{ $si->invoice_date->format('Y-m-d') }} · <b>{{ __('Due') }}:</b> {{ $si->due_date?->format('Y-m-d') }}</div>
        @if($si->order)<div><b>PO:</b> <a href="{{ route('purchase-orders.show', $si->order) }}">{{ $si->order->number }}</a></div>@endif
        @if($si->receipt)<div><b>GRN:</b> <a href="{{ route('goods-receipts.show', $si->receipt) }}">{{ $si->receipt->number }}</a> ({{ money($si->receipt->total) }})</div>@endif
        <div class="mt-2"><b>{{ __('Paid') }}:</b> {{ money($si->paid_amount) }} · <b>{{ __('Balance') }}:</b> {{ money($si->balanceDue()) }}</div>
        @foreach($si->allocations as $a)<div><a href="{{ route('payments.show', $a->payment) }}">{{ $a->payment->number }}</a> {{ money($a->amount) }}</div>@endforeach
    </div></div></div>
</div>
@endsection
