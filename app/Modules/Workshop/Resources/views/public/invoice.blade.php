@extends('layouts.public')
@section('title', __('Invoice verification'))
@section('content')
<div class="card mx-auto text-center" style="max-width:520px">
    <div class="card-body">
        <i class="bi bi-patch-check-fill text-success" style="font-size:3rem"></i>
        <h5 class="mt-2">{{ __('This is an authentic tax invoice') }}</h5>
        <table class="table table-sm mt-3 text-start">
            <tr><th>{{ __('Seller') }}</th><td>{{ $company->displayName() }}</td></tr>
            <tr><th>{{ __('VAT number') }}</th><td>{{ $company->vat_number ?? '—' }}</td></tr>
            <tr><th>{{ __('Invoice number') }}</th><td>{{ $inv->number }}</td></tr>
            <tr><th>{{ __('Date') }}</th><td>{{ $inv->issue_date->format('Y-m-d') }}</td></tr>
            <tr><th>{{ __('Total incl. VAT') }}</th><td>{{ money($inv->total) }}</td></tr>
            <tr><th>{{ __('VAT') }}</th><td>{{ money($inv->tax_total) }}</td></tr>
            <tr><th>{{ __('Status') }}</th><td>{!! status_badge($inv->status) !!}</td></tr>
        </table>
    </div>
</div>
@endsection
