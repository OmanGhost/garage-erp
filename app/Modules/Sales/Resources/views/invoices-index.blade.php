@extends('layouts.app')
@section('title', $resource->title())
@section('content')
@php
    $stats = $resource->stats();
    $current = (string) request('f_status');
    $tabs = ['' => __('All'), 'draft' => status_label('draft'), 'held' => status_label('held'), 'posted' => status_label('posted'), 'partially_paid' => status_label('partially_paid'), 'paid' => status_label('paid'), 'cancelled' => status_label('cancelled')];
    $tone = ['draft' => 'secondary', 'held' => 'warning', 'posted' => 'primary', 'partially_paid' => 'warning', 'paid' => 'success', 'cancelled' => 'danger'];
    $typeIcon = ['sale' => 'bi-bag', 'pos' => 'bi-shop', 'workshop' => 'bi-tools', 'insurance' => 'bi-shield-plus', 'fleet' => 'bi-truck-front', 'store' => 'bi-globe2'];
    $filters = array_values(array_filter($resource->filters(), fn ($f) => $f->name !== 'status'));
@endphp
<x-page-header :title="$resource->title()" :icon="$resource::$icon" :breadcrumbs="[$resource->title()]">
    @can('pos.view')<a href="{{ route('pos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-shop"></i> {{ __('POS') }}</a>@endcan
    @if($resource->can('create') && $resource->createUrl())
        <a href="{{ $resource->createUrl() }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New invoice') }}</a>
    @endif
</x-page-header>

<div class="row g-2 mb-3">
    @foreach([
        [__('Sales this month'), money($stats['month']), trans_choice('{0} no invoices|{1} 1 invoice|[2,*] :n invoices', $stats['month_count'], ['n' => $stats['month_count']]), 'bi-graph-up-arrow', 'primary'],
        [__('Outstanding'), money($stats['outstanding']), trans_choice('{0} no invoices|{1} 1 invoice|[2,*] :n invoices', $stats['outstanding_count'], ['n' => $stats['outstanding_count']]), 'bi-hourglass-split', 'warning'],
        [__('Overdue'), money($stats['overdue']), trans_choice('{0} no invoices|{1} 1 invoice|[2,*] :n invoices', $stats['overdue_count'], ['n' => $stats['overdue_count']]), 'bi-exclamation-octagon', 'danger'],
        [__('Drafts & held'), number_format($stats['drafts']), __('Not posted yet'), 'bi-pencil-square', 'secondary'],
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
                <th>{{ __('Invoice') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Date') }}</th>
                <th class="text-end">{{ __('Total') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $posted = $row->isPosted();
                    $bal = $posted ? $row->balanceDue() : null;
                    $pct = $posted && (float) $row->total > 0 ? max(0, min(100, round(100 - (float) $bal * 100 / (float) $row->total))) : 0;
                    $late = $posted && \App\Support\Money::isPositive($bal) && $row->due_date && $row->due_date->lt(today());
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('invoices.show', $row) }}" class="doc-row-title text-decoration-none">{{ $row->displayNumber() }}</a>
                        <div class="small text-body-secondary"><i class="bi {{ $typeIcon[$row->type] ?? 'bi-receipt' }}"></i> {{ status_label($row->type) }} · {{ status_label($row->payment_terms) }}</div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="party-avatar">{{ mb_substr($row->customer?->name ?? '?', 0, 1) }}</span>
                            <div class="min-w-0">
                                <div class="text-truncate" style="max-width:220px">{{ $row->customer?->name }}</div>
                                @if($row->jobCard)<div class="small text-body-secondary">{{ $row->jobCard->number }} · {{ $row->jobCard->vehicle?->plate() }}</div>@endif
                            </div>
                        </div>
                    </td>
                    <td class="small">{{ $row->issue_date?->format('Y-m-d') }}@if($row->due_date && $posted)<div class="{{ $late ? 'text-danger fw-semibold' : 'text-body-secondary' }}">{{ __('Due') }} {{ $row->due_date->format('Y-m-d') }}</div>@endif</td>
                    <td class="text-end font-monospace fw-semibold">{{ money($row->total, false) }}</td>
                    <td style="min-width:120px">
                        @if($posted)
                            <div class="pay-bar"><span style="width:{{ $pct }}%"></span></div>
                            <div class="small mt-1 {{ \App\Support\Money::isPositive($bal) ? 'text-danger' : 'text-success' }}">@if(\App\Support\Money::isPositive($bal)){{ __('Due') }} <span class="font-monospace">{{ money($bal, false) }}</span>@else<i class="bi bi-check2"></i> {{ __('Settled') }}@endif</div>
                        @else<span class="small text-body-secondary">—</span>@endif
                    </td>
                    <td><span class="badge text-bg-{{ $tone[$row->status] ?? 'secondary' }}">{{ status_label($row->status) }}</span>@if($late) <span class="badge text-bg-danger-subtle text-danger-emphasis">{{ __('Overdue') }}</span>@endif</td>
                    <td class="table-actions text-nowrap">
                        @foreach($resource->rowActions($row) as $a)
                            <a href="{{ $a['url'] }}" class="btn btn-sm btn-light" title="{{ $a['label'] }}" aria-label="{{ $a['label'] }}" @if(! empty($a['blank'])) target="_blank" rel="noopener" @endif><i class="bi {{ $a['icon'] ?? 'bi-lightning' }}"></i></a>
                        @endforeach
                        @if($resource->can('update') && $resource->canEditRecord($row) && $resource->editUrl($row))
                            <a href="{{ $resource->editUrl($row) }}" class="btn btn-sm btn-light" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                        @endif
                        @if($resource->can('delete') && ! $resource->deleteBlocker($row))
                            <form method="post" action="{{ route('crud.destroy', [$slug, $row->getKey()]) }}" class="d-inline" data-confirm="{{ __('Delete «:name»? This cannot be undone.', ['name' => $resource->recordLabel($row)]) }}">@csrf @method('delete')
                                <button class="btn btn-sm btn-light text-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                        <a href="{{ route('invoices.show', $row) }}" class="btn btn-sm btn-primary">{{ __('Open') }}</a>
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
