@extends('layouts.app')
@section('title', $s->name)
@section('content')
<x-page-header :title="$s->name" icon="bi-shop" :breadcrumbs="[__('Suppliers') => route('crud.index', 'suppliers'), $s->name]" :subtitle="e($s->code).' · '.e($s->phone).' · '.__('Rating').' '.qty($s->rating).'/5'">
    @can('suppliers.update')<a href="{{ route('crud.edit', ['suppliers', $s->id]) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>@endcan
    <a href="{{ route('suppliers.show', [$s, 'print' => 1, 'from' => $from, 'to' => $to]) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Statement') }}</a>
    @can('payments.create')<a href="{{ route('payments.pay', ['supplier_id' => $s->id]) }}" class="btn btn-danger"><i class="bi bi-box-arrow-up"></i> {{ __('Pay supplier') }}</a>@endcan
    @can('purchase_orders.create')<a href="{{ route('purchase-orders.create') }}" class="btn btn-primary"><i class="bi bi-cart-plus"></i> {{ __('New PO') }}</a>@endcan
</x-page-header>
<div class="row g-3 mb-3">
    @foreach([[__('Payable balance'), money($s->balance())], [__('Total spend'), money($kpi['spend'])], [__('Orders'), $kpi['orders']], [__('On-time delivery'), $kpi['on_time_rate'] !== null ? $kpi['on_time_rate'].'%' : '—'], [__('Avg. lead time'), $kpi['avg_lead_days'] !== null ? $kpi['avg_lead_days'].' '.__('days') : '—'], [__('Returns'), $kpi['returns']]] as [$l, $v])
        <div class="col-6 col-md-4 col-xl-2"><div class="card kpi"><div class="card-body py-2"><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-5">{{ $v }}</div></div></div></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-xl-7"><div class="card"><div class="card-header d-flex">{{ __('Account statement') }}
        <form class="ms-auto d-flex gap-1"><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"><button class="btn btn-sm btn-outline-secondary">{{ __('Apply') }}</button></form></div>
        <div class="table-responsive"><table class="table table-sm table-erp mb-0">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Document') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
            <tbody><tr class="table-light"><td colspan="5">{{ __('Opening balance') }}</td><td class="text-end font-monospace">{{ money($st['opening'], false) }}</td></tr>
            @foreach($st['rows'] as $r)<tr><td>{{ $r->date }}</td><td>{{ $r->number }}</td><td class="small">{{ $r->line_description ?: $r->description }}</td><td class="text-end font-monospace">{{ (float) $r->debit ? money($r->debit, false) : '' }}</td><td class="text-end font-monospace">{{ (float) $r->credit ? money($r->credit, false) : '' }}</td><td class="text-end font-monospace">{{ money($r->balance, false) }}</td></tr>@endforeach</tbody>
        </table></div></div></div>
    <div class="col-xl-5">
        <div class="card mb-3"><div class="card-header">{{ __('Purchase orders') }}</div><ul class="list-group list-group-flush small">
            @forelse($orders as $o)<li class="list-group-item d-flex"><a href="{{ route('purchase-orders.show', $o) }}">{{ $o->number }}</a><span class="ms-2 text-body-secondary">{{ $o->order_date->format('Y-m-d') }}</span><span class="ms-auto me-2 font-monospace">{{ money($o->total, false) }}</span>{!! status_badge($o->status) !!}</li>@empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul></div>
        <div class="card"><div class="card-header">{{ __('Supplier invoices') }}</div><ul class="list-group list-group-flush small">
            @forelse($invoices as $i)<li class="list-group-item d-flex"><a href="{{ route('supplier-invoices.show', $i) }}">{{ $i->number }}</a><span class="ms-2 text-body-secondary">{{ $i->supplier_invoice_number }}</span><span class="ms-auto me-2 font-monospace">{{ money($i->balanceDue(), false) }}</span>{!! status_badge($i->status) !!}</li>@empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul></div>
    </div>
</div>
@endsection
