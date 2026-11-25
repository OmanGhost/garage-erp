@extends('layouts.app')
@section('title', __('Pay supplier'))
@section('content')
<x-page-header :title="__('Payment voucher — pay supplier')" icon="bi-box-arrow-up" :breadcrumbs="[__('Payments') => route('crud.index', 'payments'), __('Pay')]"/>
@if(! $supplier)
    <form method="get" class="card mx-auto" style="max-width:560px"><div class="card-body">
        <label class="form-label">{{ __('Supplier') }}</label>
        <div x-data @lookup-selected="$el.closest('form').submit()"><x-lookup name="supplier_id" type="suppliers" required/></div>
    </div></form>
@else
<form method="post" action="{{ route('payments.pay') }}">@csrf <x-idem/>
    <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
    <div class="row g-3">
        <div class="col-lg-5"><div class="card"><div class="card-body row g-2">
            <div class="col-12"><div class="fw-semibold">{{ $supplier->name }}</div><div class="small text-body-secondary">{{ __('Payable balance') }}: <b>{{ money($supplier->balance()) }}</b></div></div>
            <div class="col-6"><label class="form-label required">{{ __('Amount') }}</label><input type="number" name="amount" step="0.001" min="0.001" class="form-control form-control-lg font-monospace" required></div>
            <div class="col-6"><label class="form-label">{{ __('Date') }}</label><input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required></div>
            <div class="col-6"><label class="form-label">{{ __('Method') }}</label><select name="method" class="form-select">@foreach(\App\Support\Options::PAYMENT_METHODS as $m)<option value="{{ $m }}" @selected($m === 'bank_transfer')>{{ status_label($m) }}</option>@endforeach</select></div>
            <div class="col-6"><label class="form-label">{{ __('Paid from') }}</label><select name="bank_account_id" class="form-select" required>@foreach($banks as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
            <div class="col-6"><label class="form-label">{{ __('Reference') }}</label><input name="reference" class="form-control"></div>
            <div class="col-3"><label class="form-label">{{ __('Cheque no.') }}</label><input name="cheque_number" class="form-control"></div>
            <div class="col-3"><label class="form-label">{{ __('Cheque date') }}</label><input type="date" name="cheque_date" class="form-control"></div>
            <div class="col-12"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
            <div class="col-12"><button class="btn btn-danger btn-lg w-100"><i class="bi bi-check2-circle"></i> {{ __('Record payment') }}</button></div>
        </div></div></div>
        <div class="col-lg-7"><div class="card"><div class="card-header">{{ __('Allocate to supplier invoices') }}</div>
            <div class="table-responsive"><table class="table table-erp mb-0">
                <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Supplier no.') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Balance') }}</th><th style="width:140px">{{ __('Allocate') }}</th></tr></thead>
                <tbody>@forelse($open as $si)<tr><td><a href="{{ route('supplier-invoices.show', $si) }}">{{ $si->number }}</a></td><td>{{ $si->supplier_invoice_number }}</td><td>{{ $si->due_date?->format('Y-m-d') }}</td><td class="text-end font-monospace">{{ money($si->balanceDue(), false) }}</td>
                    <td><input type="number" step="0.001" min="0" max="{{ $si->balanceDue() }}" name="allocations[{{ $si->id }}]" class="form-control form-control-sm"></td></tr>
                @empty<tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No open supplier invoices. Leave empty to auto allocate.') }}</td></tr>@endforelse</tbody>
            </table></div></div></div>
    </div>
</form>
@endif
@endsection
