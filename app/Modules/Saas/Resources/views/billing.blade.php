@extends('layouts.admin')
@section('title', __('Billing'))
@section('content')
@php
    $stColor = ['unpaid' => 'warning', 'overdue' => 'danger', 'paid' => 'success', 'cancelled' => 'secondary'];
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-receipt text-brand"></i> {{ __('Billing') }}</h4>
        <div class="small text-body-secondary">{{ __('Tax invoices to clients: subscription renewals and other charges.') }}</div>
    </div>
    <a class="btn btn-primary" href="{{ route('admin.billing.create') }}"><i class="bi bi-plus-lg"></i> {{ __('New invoice') }}</a>
</div>

<div class="row g-2 mb-3">
    @foreach([
        [__('Unpaid'), money($stats['unpaid']), trans_choice('{0} no invoices|{1} 1 invoice|[2,*] :n invoices', $stats['unpaid_count'], ['n' => $stats['unpaid_count']]), 'bi-hourglass-split', 'warning', 'unpaid'],
        [__('Overdue'), money($stats['overdue']), trans_choice('{0} no invoices|{1} 1 invoice|[2,*] :n invoices', $stats['overdue_count'], ['n' => $stats['overdue_count']]), 'bi-exclamation-octagon', 'danger', 'overdue'],
        [__('Paid this month'), money($stats['paid_month']), now()->translatedFormat('F Y'), 'bi-cash-coin', 'success', 'paid'],
        [__('Paid this year'), money($stats['paid_year']), now()->format('Y'), 'bi-graph-up-arrow', 'primary', null],
    ] as [$l, $v, $sub, $icon, $c, $filter])
        <div class="col-6 col-lg-3">
            <a class="card kpi h-100 text-decoration-none {{ $filter && request('status') === $filter ? 'border-'.$c.' border-2' : '' }}" href="{{ $filter ? route('admin.billing.index', ['status' => $filter]) : route('admin.billing.index') }}">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="kpi-icon bg-{{ $c }}-subtle text-{{ $c }}"><i class="bi {{ $icon }}"></i></span>
                    <div class="min-w-0"><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-5">{{ $v }}</div><div class="small text-body-secondary">{{ $sub }}</div></div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<form class="card card-body py-2 mb-3" method="get">
    <div class="row g-2 align-items-center">
        <div class="col-md-3"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="{{ __('Invoice number or client...') }}" aria-label="{{ __('Search') }}"></div>
        <div class="col-md-3"><select name="company_id" class="form-select form-select-sm" aria-label="{{ __('Client') }}"><option value="">{{ __('All clients') }}</option>@foreach($clients as $id => $n)<option value="{{ $id }}" @selected((string) request('company_id') === (string) $id)>{{ $n }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option>@foreach(['unpaid', 'overdue', 'paid', 'cancelled'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ status_label($s) }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><input type="date" class="form-control form-control-sm" name="from" value="{{ request('from') }}" aria-label="{{ __('From') }}"></div>
        <div class="col-md-2 d-grid"><button class="btn btn-sm btn-primary"><i class="bi bi-search"></i> {{ __('Search') }}</button></div>
    </div>
</form>

<div class="card mb-4">
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light small"><tr><th>{{ __('Invoice') }}</th><th>{{ __('Client') }}</th><th>{{ __('Date') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th><th class="text-end"></th></tr></thead>
        <tbody>
        @forelse($invoices as $inv)
            @php $st = $inv->stage(); @endphp
            <tr>
                <td><a class="fw-semibold font-monospace" href="{{ route('admin.billing.show', $inv) }}">{{ $inv->number }}</a>
                    @if($inv->period_start)<div class="small text-body-secondary">{{ $inv->period_start->format('Y-m-d') }} → {{ $inv->period_end?->format('Y-m-d') }}</div>@endif</td>
                <td><a href="{{ route('admin.clients.show', $inv->company_id) }}">{{ $inv->company?->name }}</a></td>
                <td class="small">{{ $inv->issue_date?->format('Y-m-d') }}</td>
                <td class="small {{ $st === 'overdue' ? 'text-danger fw-semibold' : '' }}">{{ $inv->due_date?->format('Y-m-d') }}</td>
                <td class="text-end font-monospace fw-semibold">{{ money($inv->amount) }}</td>
                <td><span class="badge text-bg-{{ $stColor[$st] ?? 'secondary' }}">{{ status_label($st) }}</span>@if($inv->paid_at)<div class="small text-body-secondary">{{ $inv->paid_at->format('Y-m-d') }}</div>@endif</td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.billing.print', $inv) }}" target="_blank" rel="noopener" title="{{ __('Print') }}" aria-label="{{ __('Print') }}"><i class="bi bi-printer"></i></a>
                    <a class="btn btn-sm btn-primary" href="{{ route('admin.billing.show', $inv) }}">{{ __('Open') }}</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-body-secondary py-5"><i class="bi bi-receipt fs-1 d-block mb-2"></i>{{ __('No invoices.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
<div class="mb-4">{{ $invoices->links() }}</div>

{{-- invoice header & defaults --}}
<div class="card" id="billing-settings">
    <div class="card-header"><i class="bi bi-gear"></i> {{ __('Invoice details (platform)') }}</div>
    <div class="card-body">
        <form method="post" action="{{ route('admin.billing.settings') }}" class="row g-2">@csrf
            <div class="col-md-6"><label class="form-label small mb-0" for="b_name">{{ __('Legal name on invoices') }}</label><input id="b_name" name="billing_name" class="form-control" value="{{ $settings['billing_name'] }}" placeholder="{{ config('app.name') }}"></div>
            <div class="col-md-3"><label class="form-label small mb-0" for="b_vat">{{ __('VAT number') }}</label><input id="b_vat" name="billing_vat_number" class="form-control" dir="ltr" value="{{ $settings['billing_vat_number'] }}"></div>
            <div class="col-md-3"><label class="form-label small mb-0" for="b_cr">{{ __('C.R. number') }}</label><input id="b_cr" name="billing_cr_number" class="form-control" dir="ltr" value="{{ $settings['billing_cr_number'] }}"></div>
            <div class="col-md-6"><label class="form-label small mb-0" for="b_addr">{{ __('Address') }}</label><input id="b_addr" name="billing_address" class="form-control" value="{{ $settings['billing_address'] }}"></div>
            <div class="col-md-3"><label class="form-label small mb-0" for="b_email">{{ __('Email') }}</label><input id="b_email" name="billing_email" type="email" class="form-control" dir="ltr" value="{{ $settings['billing_email'] }}"></div>
            <div class="col-md-3"><label class="form-label small mb-0" for="b_phone">{{ __('Phone') }}</label><input id="b_phone" name="billing_phone" class="form-control" dir="ltr" value="{{ $settings['billing_phone'] }}"></div>
            <div class="col-6 col-md-3"><label class="form-label small mb-0" for="b_tax">{{ __('Default VAT %') }}</label><input id="b_tax" name="billing_tax_rate" type="number" step="0.01" min="0" max="100" class="form-control" value="{{ $settings['billing_tax_rate'] }}" required></div>
            <div class="col-6 col-md-3"><label class="form-label small mb-0" for="b_due">{{ __('Payment due (days)') }}</label><input id="b_due" name="billing_due_days" type="number" min="0" max="120" class="form-control" value="{{ $settings['billing_due_days'] }}" required></div>
            <div class="col-md-6"><label class="form-label small mb-0" for="b_bank">{{ __('Bank details (printed on invoices)') }}</label><textarea id="b_bank" name="billing_bank_details" rows="2" class="form-control">{{ $settings['billing_bank_details'] }}</textarea></div>
            <div class="col-12"><label class="form-label small mb-0" for="b_terms">{{ __('Terms / footer') }}</label><textarea id="b_terms" name="billing_terms" rows="2" class="form-control">{{ $settings['billing_terms'] }}</textarea></div>
            <div class="col-12"><button class="btn btn-primary"><i class="bi bi-check2"></i> {{ __('Save') }}</button></div>
        </form>
    </div>
</div>
@endsection
