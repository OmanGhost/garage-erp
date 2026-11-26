@extends('layouts.app')
@section('title', $resource->title())
@section('content')
@php
    $stats = $resource->stats();
    $current = (string) request('f_status');
    $tabs = ['' => __('All'), 'draft' => status_label('draft'), 'posted' => status_label('posted'), 'partially_paid' => status_label('partially_paid'), 'paid' => status_label('paid'), 'cancelled' => status_label('cancelled')];
    $tone = ['draft' => 'secondary', 'posted' => 'primary', 'partially_paid' => 'warning', 'paid' => 'success', 'cancelled' => 'danger'];
    $filters = array_values(array_filter($resource->filters(), fn ($f) => $f->name !== 'status'));
    $count = fn ($n) => trans_choice('{0} no invoices|{1} 1 invoice|[2,*] :n invoices', $n, ['n' => $n]);
@endphp
<x-page-header :title="$resource->title()" :icon="$resource::$icon" :breadcrumbs="[__('Purchasing'), $resource->title()]">
    @can('purchase_orders.view')<a href="{{ route('crud.index', 'purchase-orders') }}" class="btn btn-outline-secondary"><i class="bi bi-cart-check"></i> {{ __('Purchase orders') }}</a>@endcan
    @can('payments.create')<a href="{{ route('payments.pay') }}" class="btn btn-outline-danger"><i class="bi bi-cash-stack"></i> {{ __('Pay supplier') }}</a>@endcan
    @if($resource->can('create') && $resource->createUrl())
        <a href="{{ $resource->createUrl() }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New supplier invoice') }}</a>
    @endif
</x-page-header>

<div class="row g-2 mb-3">
    @foreach([
        [__('Purchases this month'), money($stats['month']), $count($stats['month_count']), 'bi-bag-check', 'primary'],
        [__('Payable to suppliers'), money($stats['payable']), $count($stats['payable_count']), 'bi-wallet2', 'warning'],
        [__('Overdue'), money($stats['overdue']), $count($stats['overdue_count']), 'bi-exclamation-octagon', 'danger'],
        [__('Due within 7 days'), money($stats['due_week']), $count($stats['due_week_count']), 'bi-calendar-week', 'info'],
    ] as [$l, $v, $sub, $icon, $c])
        <div class="col-6 col-lg-3">
            <div class="card kpi h-100"><div class="card-body d-flex align-items-center gap-3">
                <span class="kpi-icon bg-{{ $c }}-subtle text-{{ $c }}"><i class="bi {{ $icon }}"></i></span>
                <div class="min-w-0"><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-5">{{ $v }}</div><div class="small text-body-secondary">{{ $sub }}</div></div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="card">
    <nav class="status-tabs" aria-label="{{ __('Status') }}">
        @foreach($tabs as $k => $label)
            @php $n = $k === '' ? array_sum($stats['by_status']) : ($stats['by_status'][$k] ?? 0); @endphp
            @if($k === '' || $n)
                <a href="{{ request()->fullUrlWithQuery(['f_status' => $k ?: null, 'page' => null]) }}" class="{{ $current === $k ? 'active' : '' }}">{{ $label }} <span class="badge rounded-pill text-bg-{{ $current === $k ? 'primary' : 'light' }}">{{ number_format($n) }}</span></a>
            @endif
        @endforeach
    </nav>
    @include('crud._filters', ['filters' => $filters, 'keep' => ['f_status' => $current]])
    <div class="table-responsive">
        <table class="table table-hover table-erp align-middle mb-0">
            <thead><tr>
                <th>{{ __('Invoice') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Date') }}</th>
                <th class="text-end">{{ __('Total') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $open = in_array($row->status, ['posted', 'partially_paid', 'paid'], true);
                    $bal = $open ? $row->balanceDue() : null;
                    $pct = $open && (float) $row->total > 0 ? max(0, min(100, round((float) $row->paid_amount * 100 / (float) $row->total))) : 0;
                    $late = $open && \App\Support\Money::isPositive($bal) && $row->due_date && $row->due_date->lt(today());
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('supplier-invoices.show', $row) }}" class="doc-row-title text-decoration-none">{{ $row->number }}</a>
                        <div class="small text-body-secondary">@if($row->supplier_invoice_number){{ __('Ref') }} <bdi dir="ltr">{{ $row->supplier_invoice_number }}</bdi>@endif @if($row->order) · {{ $row->order->number }}@endif</div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="party-avatar">{{ mb_substr($row->supplier?->name ?? '?', 0, 1) }}</span>
                            <div class="min-w-0"><div class="text-truncate" style="max-width:220px">{{ $row->supplier?->name }}</div>@if($row->receipt)<div class="small text-body-secondary">GRN {{ $row->receipt->number }}</div>@endif</div>
                        </div>
                    </td>
                    <td class="small">{{ $row->invoice_date?->format('Y-m-d') }}@if($row->due_date)<div class="{{ $late ? 'text-danger fw-semibold' : 'text-body-secondary' }}">{{ __('Due') }} {{ $row->due_date->format('Y-m-d') }}</div>@endif</td>
                    <td class="text-end font-monospace fw-semibold">{{ money($row->total, false) }}</td>
                    <td style="min-width:120px">
                        @if($open)
                            <div class="pay-bar"><span style="width:{{ $pct }}%"></span></div>
                            <div class="small mt-1 {{ \App\Support\Money::isPositive($bal) ? 'text-danger' : 'text-success' }}">@if(\App\Support\Money::isPositive($bal)){{ __('Due') }} <span class="font-monospace">{{ money($bal, false) }}</span>@else<i class="bi bi-check2"></i> {{ __('Settled') }}@endif</div>
                        @else<span class="small text-body-secondary">—</span>@endif
                    </td>
                    <td><span class="badge text-bg-{{ $tone[$row->status] ?? 'secondary' }}">{{ status_label($row->status) }}</span>@if($late) <span class="badge text-bg-danger-subtle text-danger-emphasis">{{ __('Overdue') }}</span>@endif</td>
                    <td class="table-actions text-nowrap">
                        @if($resource->can('delete') && ! $resource->deleteBlocker($row))
                            <form method="post" action="{{ route('crud.destroy', [$slug, $row->getKey()]) }}" class="d-inline" data-confirm="{{ __('Delete «:name»? This cannot be undone.', ['name' => $resource->recordLabel($row)]) }}">@csrf @method('delete')
                                <button class="btn btn-sm btn-light text-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                        <a href="{{ route('supplier-invoices.show', $row) }}" class="btn btn-sm btn-primary">{{ __('Open') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-receipt"></i>{{ __('No records found.') }}</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('crud._pager')
</div>
@endsection
