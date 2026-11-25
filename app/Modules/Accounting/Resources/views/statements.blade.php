@extends('layouts.app')
@section('title', __('Financial Statements'))
@section('content')
@php $tabs = ['trial' => __('Trial balance'), 'income' => __('Income statement (P&L)'), 'balance' => __('Balance sheet'), 'cashflow' => __('Cash flow'), 'ar_aging' => __('Receivables aging'), 'ap_aging' => __('Payables aging'), 'vat' => __('VAT return')]; @endphp
<x-page-header :title="__('Financial Statements')" icon="bi-file-bar-graph" :breadcrumbs="[__('Accounting'), __('Financial Statements')]">
    <div class="btn-group">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn btn-outline-secondary">Excel</a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" target="_blank" class="btn btn-outline-secondary">PDF</a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'print']) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i></a>
    </div>
</x-page-header>
<ul class="nav nav-pills mb-3 flex-wrap gap-1">@foreach($tabs as $k => $l)<li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="{{ route('accounting.statements', ['tab' => $k, 'from' => $from, 'to' => $to]) }}">{{ $l }}</a></li>@endforeach</ul>
<form class="card mb-3"><div class="card-body row g-2 align-items-end">
    <input type="hidden" name="tab" value="{{ $tab }}">
    @if(!in_array($tab, ['balance', 'ar_aging', 'ap_aging']))<div class="col-auto"><label class="form-label small">{{ __('From') }}</label><input type="date" name="from" value="{{ $from }}" class="form-control"></div>@endif
    <div class="col-auto"><label class="form-label small">{{ in_array($tab, ['balance', 'ar_aging', 'ap_aging']) ? __('As of') : __('To') }}</label><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
    @if(in_array($tab, ['trial', 'income']))<div class="col-auto"><label class="form-label small">{{ __('Branch') }}</label><select name="branch_id" class="form-select"><option value="">{{ __('All (consolidated)') }}</option>@foreach($branches as $id => $n)<option value="{{ $id }}" @selected($branch == $id)>{{ $n }}</option>@endforeach</select></div>@endif
    @if($tab === 'income')<div class="col-auto"><label class="form-label small">{{ __('Cost center') }}</label><select name="cost_center_id" class="form-select"><option value="">{{ __('All') }}</option>@foreach($costCenters as $id => $n)<option value="{{ $id }}" @selected(request('cost_center_id') == $id)>{{ $n }}</option>@endforeach</select></div>@endif
    <div class="col-auto"><button class="btn btn-primary">{{ __('Show') }}</button></div>
</div></form>

<div class="card"><div class="table-responsive">
@switch($tab)
    @case('trial')
        <table class="table table-sm table-erp mb-0">
            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Account') }}</th><th class="text-end">{{ __('Opening Dr') }}</th><th class="text-end">{{ __('Opening Cr') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="text-end">{{ __('Closing Dr') }}</th><th class="text-end">{{ __('Closing Cr') }}</th></tr></thead>
            <tbody>@foreach($data['rows'] as $r)<tr><td><a href="{{ route('accounting.ledger', ['account_id' => $r['id'], 'from' => $from, 'to' => $to]) }}">{{ $r['code'] }}</a></td><td>{{ $r['name'] }}</td>
                @foreach(['opening_debit', 'opening_credit', 'debit', 'credit', 'closing_debit', 'closing_credit'] as $k)<td class="text-end font-monospace">{{ (float) $r[$k] ? money($r[$k], false) : '' }}</td>@endforeach</tr>@endforeach</tbody>
            <tfoot><tr class="fw-bold"><td colspan="2">{{ __('Total') }}</td>@foreach(['opening_debit', 'opening_credit', 'debit', 'credit', 'closing_debit', 'closing_credit'] as $k)<td class="text-end font-monospace">{{ money($data['totals'][$k], false) }}</td>@endforeach</tr></tfoot>
        </table>
        @break
    @case('income')
        <table class="table table-sm table-erp mb-0">
            <tr class="table-light"><th colspan="2">{{ __('Revenue') }}</th></tr>
            @foreach($data['revenue'] as $r)<tr><td>{{ $r['code'] }} {{ $r['name'] }}</td><td class="text-end font-monospace">{{ money($r['amount'], false) }}</td></tr>@endforeach
            <tr class="fw-bold"><td>{{ __('Total revenue') }}</td><td class="text-end font-monospace">{{ money($data['total_revenue']) }}</td></tr>
            <tr class="table-light"><th colspan="2">{{ __('Cost of sales') }}</th></tr>
            @foreach($data['cogs'] as $r)<tr><td>{{ $r['code'] }} {{ $r['name'] }}</td><td class="text-end font-monospace">{{ money($r['amount'], false) }}</td></tr>@endforeach
            <tr class="fw-bold"><td>{{ __('Gross profit') }}</td><td class="text-end font-monospace">{{ money($data['gross_profit']) }}</td></tr>
            <tr class="table-light"><th colspan="2">{{ __('Operating expenses') }}</th></tr>
            @foreach($data['expenses'] as $r)<tr><td>{{ $r['code'] }} {{ $r['name'] }}</td><td class="text-end font-monospace">{{ money($r['amount'], false) }}</td></tr>@endforeach
            <tr class="fw-bold"><td>{{ __('Total expenses') }}</td><td class="text-end font-monospace">{{ money($data['total_expenses']) }}</td></tr>
            <tr class="fs-5 fw-bold {{ (float) $data['net_income'] >= 0 ? 'table-success' : 'table-danger' }}"><td>{{ __('Net income') }}</td><td class="text-end font-monospace">{{ money($data['net_income']) }}</td></tr>
        </table>
        @break
    @case('balance')
        <table class="table table-sm table-erp mb-0">
            @foreach(['asset' => __('Assets'), 'liability' => __('Liabilities'), 'equity' => __('Equity')] as $sec => $label)
                <tr class="table-light"><th colspan="2">{{ $label }}</th></tr>
                @foreach($data['sections'][$sec] as $r)<tr><td>{{ $r['code'] }} {{ $r['name'] }}</td><td class="text-end font-monospace">{{ money($r['amount'], false) }}</td></tr>@endforeach
                <tr class="fw-bold"><td>{{ __('Total') }} {{ $label }}</td><td class="text-end font-monospace">{{ money($data['totals'][$sec]) }}</td></tr>
            @endforeach
            <tr class="fw-bold fs-6 {{ $data['balanced'] ? 'table-success' : 'table-danger' }}"><td>{{ __('Liabilities + equity') }}</td><td class="text-end font-monospace">{{ money($data['liabilities_equity']) }}</td></tr>
        </table>
        @break
    @case('cashflow')
        <table class="table table-sm table-erp mb-0">
            <tr><td>{{ __('Opening cash') }}</td><td class="text-end font-monospace" colspan="3">{{ money($data['opening']) }}</td></tr>
            <tr class="table-light"><th>{{ __('Activity') }}</th><th class="text-end">{{ __('Inflows') }}</th><th class="text-end">{{ __('Outflows') }}</th><th class="text-end">{{ __('Net') }}</th></tr>
            @foreach($data['categories'] as $k => $c)<tr><td>{{ status_label($k) }}</td><td class="text-end font-monospace text-success">{{ money($c['in'], false) }}</td><td class="text-end font-monospace text-danger">{{ money($c['out'], false) }}</td><td class="text-end font-monospace">{{ money($c['net'], false) }}</td></tr>@endforeach
            <tr class="fw-bold"><td>{{ __('Net change in cash') }}</td><td colspan="3" class="text-end font-monospace">{{ money($data['net_change']) }}</td></tr>
            <tr class="fw-bold table-light"><td>{{ __('Closing cash') }}</td><td colspan="3" class="text-end font-monospace">{{ money($data['closing']) }}</td></tr>
        </table>
        @break
    @case('ar_aging')
    @case('ap_aging')
        <table class="table table-sm table-erp mb-0">
            <thead><tr><th>{{ $tab === 'ar_aging' ? __('Customer') : __('Supplier') }}</th><th class="text-end">{{ __('Current') }}</th><th class="text-end">1-30</th><th class="text-end">31-60</th><th class="text-end">61-90</th><th class="text-end">90+</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
            <tbody>@foreach($data['parties'] as $p)<tr><td>{{ $p['name'] }}</td>@foreach($p['buckets'] as $b)<td class="text-end font-monospace">{{ (float) $b ? money($b, false) : '' }}</td>@endforeach<td class="text-end font-monospace fw-bold">{{ money($p['total'], false) }}</td></tr>@endforeach</tbody>
            <tfoot><tr class="fw-bold"><td>{{ __('Total') }}</td>@foreach($data['totals'] as $b)<td class="text-end font-monospace">{{ money($b, false) }}</td>@endforeach<td class="text-end font-monospace">{{ money($data['grand_total']) }}</td></tr></tfoot>
        </table>
        @break
    @case('vat')
        <table class="table table-erp mb-0">
            <tr><td>{{ __('Taxable sales (net)') }}</td><td class="text-end font-monospace">{{ money($data['sales_net']) }}</td><td>{{ __('Output VAT on invoices') }}</td><td class="text-end font-monospace">{{ money($data['sales_tax']) }}</td></tr>
            <tr><td>{{ __('Credit notes (net)') }}</td><td class="text-end font-monospace">-{{ money($data['credit_net']) }}</td><td>{{ __('VAT on credit notes') }}</td><td class="text-end font-monospace">-{{ money($data['credit_tax']) }}</td></tr>
            <tr><td>{{ __('Taxable purchases (net)') }}</td><td class="text-end font-monospace">{{ money($data['purchases_net']) }}</td><td>{{ __('Input VAT on purchases') }}</td><td class="text-end font-monospace">{{ money($data['purchases_tax']) }}</td></tr>
            <tr class="table-light"><td colspan="2">{{ __('Output VAT (ledger)') }}</td><td colspan="2" class="text-end font-monospace">{{ money($data['output_vat']) }}</td></tr>
            <tr class="table-light"><td colspan="2">{{ __('Input VAT (ledger)') }}</td><td colspan="2" class="text-end font-monospace">{{ money($data['input_vat']) }}</td></tr>
            <tr class="fw-bold fs-5"><td colspan="2">{{ __('Net VAT payable to Tax Authority') }}</td><td colspan="2" class="text-end font-monospace">{{ money($data['net_payable']) }}</td></tr>
        </table>
        @break
@endswitch
</div></div>
@endsection
