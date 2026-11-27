@extends('layouts.app')
@section('title', __('Consignment stock'))
@section('content')
<x-page-header :title="__('Consignment stock')" icon="bi-boxes"/>
<h2 class="h5">{{ $w->displayName() }} ? {{ $w->consignor?->name }}</h2>
@can('stock.update')
<form class="card card-body mb-3" method="post" action="{{ route('inventory.consignment.action', [$w, 'receive']) }}">@csrf <x-idem/>
    <div class="d-flex flex-wrap gap-2 align-items-end">
        <label>{{ __('Part') }}<x-lookup name="lines[0][part_id]" type="parts" required/></label>
        <label>{{ __('Qty') }}<input name="lines[0][qty]" class="form-control" type="number" min=".001" step=".001" required></label>
        <label>{{ __('Unit cost') }}<input name="lines[0][unit_cost]" class="form-control" type="number" step=".0001" min="0"></label>
        <label>{{ __('Reference') }}<input name="reference" class="form-control" maxlength="60"></label>
        <button class="btn btn-primary">{{ __('Receive goods') }}</button>
        <button class="btn btn-outline-secondary" formaction="{{ route('inventory.consignment.action', [$w, 'return']) }}">{{ __('Return to consignor') }}</button>
    </div>
</form>
@endcan
<div class="card mb-3"><div class="table-responsive"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('Part') }}</th><th>{{ __('On hand') }}</th><th>{{ __('Avg. cost') }}</th></tr></thead>
    <tbody>@forelse($stock as $level)
        <tr><td>{{ $level->part?->label() }}</td><td>{{ qty($level->qty_on_hand) }}</td><td>{{ money($level->avg_cost) }}</td></tr>
    @empty<tr><td colspan="3">{{ __('No records') }}</td></tr>@endforelse</tbody>
</table></div></div>
<h2 class="h5">{{ __('Unsettled sales at cost') }}</h2>
<div class="card mb-3"><div class="table-responsive"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Cost') }}</th></tr></thead>
    <tbody>@forelse($sold as $s)
        <tr><td>{{ $s['part']->label() }}</td><td>{{ qty($s['qty']) }}</td><td>{{ money($s['cost']) }}</td></tr>
    @empty<tr><td colspan="3">{{ __('No records') }}</td></tr>@endforelse</tbody>
</table></div></div>
@if($sold) @can('supplier_invoices.create')
<form class="d-flex gap-2 mb-3" method="post" action="{{ route('inventory.consignment.action', [$w, 'settle']) }}">@csrf <x-idem/>
    <input class="form-control" name="supplier_invoice_number" placeholder="{{ __('Supplier invoice number') }}" maxlength="60">
    <button class="btn btn-primary">{{ __('Create settlement') }}</button>
</form>
@endcan @endif
<div class="card mb-3"><div class="table-responsive"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Supplier invoice') }}</th><th>{{ __('Amount') }}</th></tr></thead>
    <tbody>@forelse($settlements as $s)
        <tr><td>{{ $s->period_to->toDateTimeString() }}</td><td><a href="{{ route('supplier-invoices.show', $s->supplier_invoice_id) }}">{{ $s->supplierInvoice?->number }}</a></td><td>{{ money($s->amount) }}</td></tr>
    @empty<tr><td colspan="3">{{ __('No records') }}</td></tr>@endforelse</tbody>
</table></div></div>
@endsection
