@extends('layouts.app')
@section('title', __('New supplier invoice'))
@section('content')
<x-page-header :title="__('Supplier invoice (services / expenses)')" icon="bi-receipt" :breadcrumbs="[__('Supplier Invoices') => route('crud.index', 'supplier-invoices'), __('New')]"
    :subtitle="__('For stock purchases, register the invoice from the goods receipt instead.')"/>
<form method="post" action="{{ route('supplier-invoices.store') }}" class="card" x-data="{ lines: [{d:'', q:1, c:0, t:5, a:''}] }">@csrf <x-idem/>
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label required">{{ __('Supplier') }}</label><x-lookup name="supplier_id" type="suppliers" required/></div>
        <div class="col-md-3"><label class="form-label required">{{ __('Supplier invoice no.') }}</label><input name="supplier_invoice_number" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">{{ __('Date') }}</label><input type="date" name="invoice_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
        <div class="col-md-3"><label class="form-label">{{ __('Due date') }}</label><input type="date" name="due_date" class="form-control"></div>
        <div class="col-12">
            <table class="table table-sm"><thead><tr><th>{{ __('Account') }}</th><th>{{ __('Description') }}</th><th style="width:90px">{{ __('Qty') }}</th><th style="width:120px">{{ __('Unit cost') }}</th><th style="width:90px">{{ __('VAT %') }}</th><th></th></tr></thead>
                <tbody><template x-for="(l, i) in lines" :key="i"><tr>
                    <td><select class="form-select form-select-sm" :name="`lines[${i}][account_id]`" x-model="l.a" required><option value="">—</option>@foreach($accounts as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></td>
                    <td><input class="form-control form-control-sm" :name="`lines[${i}][description]`" x-model="l.d" required></td>
                    <td><input type="number" step="0.001" class="form-control form-control-sm" :name="`lines[${i}][qty]`" x-model="l.q"></td>
                    <td><input type="number" step="0.001" class="form-control form-control-sm" :name="`lines[${i}][unit_cost]`" x-model="l.c"></td>
                    <td><input type="number" step="0.001" class="form-control form-control-sm" :name="`lines[${i}][tax_rate]`" x-model="l.t"></td>
                    <td><button type="button" class="btn btn-sm btn-link text-danger" @click="lines.splice(i,1)"><i class="bi bi-x"></i></button></td>
                </tr></template></tbody></table>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="lines.push({d:'', q:1, c:0, t:5, a:''})">+ {{ __('Add line') }}</button>
        </div>
        <div class="col-12"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Save') }}</button></div>
</form>
@endsection
