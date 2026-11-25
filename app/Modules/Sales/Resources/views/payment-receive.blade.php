@extends('layouts.app')
@section('title', __('Receive payment'))
@section('content')
<x-page-header :title="__('Receipt voucher — receive from customer')" icon="bi-box-arrow-in-down" :breadcrumbs="[__('Payments') => route('crud.index', 'payments'), __('Receive')]"/>
@if(! $customer)
    <form method="get" class="card mx-auto" style="max-width:560px"><div class="card-body">
        <label class="form-label">{{ __('Customer') }}</label>
        <div x-data @lookup-selected="$el.closest('form').submit()"><x-lookup name="customer_id" type="customers" required/></div>
    </div></form>
@else
<form method="post" action="{{ route('payments.receive') }}" x-data="{ amount: '', allocs: {}, get allocated() { return Object.values(this.allocs).reduce((s, v) => s + (Number(v) || 0), 0) } }">
    @csrf <x-idem/>
    <input type="hidden" name="customer_id" value="{{ $customer->id }}">
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card"><div class="card-body row g-2">
                <div class="col-12"><div class="fw-semibold">{{ $customer->name }}</div><div class="small text-body-secondary">{{ __('Current balance') }}: <b>{{ money($customer->balance()) }}</b></div></div>
                <div class="col-6"><label class="form-label required">{{ __('Amount') }}</label><input type="number" name="amount" step="0.001" min="0.001" class="form-control form-control-lg font-monospace" x-model="amount" required></div>
                <div class="col-6"><label class="form-label">{{ __('Date') }}</label><input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                <div class="col-6"><label class="form-label">{{ __('Method') }}</label><select name="method" class="form-select">@foreach(\App\Support\Options::PAYMENT_METHODS as $m)<option value="{{ $m }}">{{ status_label($m) }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label">{{ __('Deposit to') }}</label><select name="bank_account_id" class="form-select" required>@foreach($banks as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label">{{ __('Reference') }}</label><input name="reference" class="form-control"></div>
                <div class="col-3"><label class="form-label">{{ __('Cheque no.') }}</label><input name="cheque_number" class="form-control"></div>
                <div class="col-3"><label class="form-label">{{ __('Cheque date') }}</label><input type="date" name="cheque_date" class="form-control"></div>
                <div class="col-12"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="auto_allocate" value="1" id="aa"><label class="form-check-label" for="aa">{{ __('Allocate automatically to oldest invoices') }}</label></div></div>
                <div class="col-12"><button class="btn btn-success btn-lg w-100"><i class="bi bi-check2-circle"></i> {{ __('Record receipt') }}</button></div>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card"><div class="card-header">{{ __('Allocate to open invoices') }} <span class="small text-body-secondary">— {{ __('allocated') }}: <b x-text="erpFmt(allocated)"></b></span></div>
                <div class="table-responsive"><table class="table table-erp mb-0 align-middle">
                    <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Balance') }}</th><th style="width:140px">{{ __('Allocate') }}</th></tr></thead>
                    <tbody>@forelse($open as $inv)
                        <tr><td><a href="{{ route('invoices.show', $inv) }}">{{ $inv->number }}</a></td><td>{{ $inv->issue_date->format('Y-m-d') }}</td><td class="{{ $inv->due_date?->isPast() ? 'text-danger' : '' }}">{{ $inv->due_date?->format('Y-m-d') }}</td>
                            <td class="text-end font-monospace">{{ money($inv->balanceDue(), false) }}</td>
                            <td><input type="number" step="0.001" min="0" max="{{ $inv->balanceDue() }}" name="allocations[{{ $inv->id }}]" class="form-control form-control-sm" x-model="allocs[{{ $inv->id }}]" x-init="@if((int) $invoiceId === $inv->id) allocs[{{ $inv->id }}] = '{{ $inv->balanceDue() }}'; amount = '{{ $inv->balanceDue() }}' @endif"></td></tr>
                    @empty<tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No open invoices. The amount will be kept as customer credit.') }}</td></tr>@endforelse</tbody>
                </table></div>
            </div>
        </div>
    </div>
</form>
@endif
@endsection
