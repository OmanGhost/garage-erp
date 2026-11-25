@extends('layouts.app')
@section('title', $grn->number)
@section('content')
<x-page-header :title="__('Goods receipt').' '.$grn->number" icon="bi-box-seam" :breadcrumbs="[__('Goods Receipts') => route('crud.index', 'goods-receipts'), $grn->number]"
    :subtitle="e($grn->supplier->name).' · '.e($grn->warehouse->displayName()).' · '.status_badge($grn->status)">
    @can('landed_costs.create')<a href="{{ route('landed-costs.create', ['goods_receipt_id' => $grn->id]) }}" class="btn btn-outline-secondary"><i class="bi bi-airplane"></i> {{ __('Add landed cost') }}</a>@endcan
    @can('purchase_returns.create')<a href="{{ route('purchase-returns.create', ['goods_receipt_id' => $grn->id]) }}" class="btn btn-outline-danger"><i class="bi bi-arrow-return-left"></i> {{ __('Return to supplier') }}</a>@endcan
</x-page-header>
<div class="row g-3">
    <div class="col-xl-8"><div class="card"><table class="table table-erp mb-0">
        <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Returned') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end">{{ __('Landed / unit') }}</th><th>{{ __('Batch / serials') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
        <tbody>@foreach($grn->items as $i)<tr><td>{{ $i->part->label() }}</td><td>{{ qty($i->qty) }}</td><td>{{ qty($i->returned_qty) }}</td><td class="text-end font-monospace">{{ money($i->unit_cost, false) }}</td><td class="text-end font-monospace">{{ money($i->landed_cost_per_unit, false) }}</td>
            <td class="small">{{ $i->batch_number }} {{ \Illuminate\Support\Str::limit($i->serial_numbers, 40) }}</td><td class="text-end font-monospace">{{ money($i->line_total, false) }}</td></tr>@endforeach</tbody>
        <tfoot><tr class="fw-bold"><td colspan="6" class="text-end">{{ __('Total') }}</td><td class="text-end font-monospace">{{ money($grn->total) }}</td></tr></tfoot>
    </table></div></div>
    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-body small">
            <div><b>{{ __('PO') }}:</b> @if($grn->order)<a href="{{ route('purchase-orders.show', $grn->order) }}">{{ $grn->order->number }}</a>@endif</div>
            <div><b>{{ __('Received') }}:</b> {{ $grn->received_at->format('Y-m-d H:i') }} · {{ $grn->supplier_reference }}</div>
            @if($grn->journal_entry_id)<div><b>{{ __('Journal') }}:</b> <a href="{{ route('journal.show', $grn->journal_entry_id) }}">#{{ $grn->journal_entry_id }}</a></div>@endif
        </div></div>
        @can('supplier_invoices.create')
        <form method="post" action="{{ route('goods-receipts.invoice', $grn) }}" class="card mb-3">@csrf <x-idem/>
            <div class="card-header">{{ __('Register supplier invoice') }}</div>
            <div class="card-body row g-2">
                <div class="col-6"><input name="supplier_invoice_number" class="form-control form-control-sm" placeholder="{{ __('Supplier invoice no.') }}" required></div>
                <div class="col-6"><input type="date" name="invoice_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required></div>
                <div class="col-12"><button class="btn btn-sm btn-primary w-100">{{ __('Create invoice from receipt') }}</button></div>
            </div>
        </form>
        @endcan
        @if($grn->landedCosts->isNotEmpty())<div class="card"><div class="card-header">{{ __('Landed costs') }}</div><ul class="list-group list-group-flush small">@foreach($grn->landedCosts as $l)<li class="list-group-item d-flex"><a href="{{ route('landed-costs.show', $l) }}">{{ $l->number }}</a><span class="ms-auto">{{ money($l->total) }} {!! status_badge($l->status) !!}</span></li>@endforeach</ul></div>@endif
    </div>
</div>
@endsection
