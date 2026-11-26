@extends('layouts.app')
@section('title', __('Inventory analytics'))
@section('content')
@php
    $tabs = ['abc' => [__('ABC analysis'), 'bi-sort-down'], 'aging' => [__('Stock aging'), 'bi-hourglass-split'], 'dead' => [__('Dead stock'), 'bi-x-octagon'], 'forecast' => [__('Demand forecast'), 'bi-graph-up'], 'cycle' => [__('Cycle counting'), 'bi-arrow-repeat']];
    $cls = ['A' => 'danger', 'B' => 'warning', 'C' => 'secondary'];
@endphp
<x-page-header :title="__('Inventory analytics')" icon="bi-graph-up" :breadcrumbs="[__('Spare Parts & Inventory'), __('Inventory analytics')]">
    @can('stock.view')<a href="{{ route('inventory.reorder') }}" class="btn btn-outline-secondary"><i class="bi bi-cart-plus"></i> {{ __('Reorder suggestions') }}</a>@endcan
</x-page-header>

{{-- automatic reordering --}}
<div class="card mb-3"><div class="card-body d-flex flex-wrap align-items-center gap-3">
    <i class="bi bi-robot fs-3 text-brand"></i>
    <div class="flex-grow-1 min-w-0">
        <div class="fw-semibold">{{ __('Automatic reordering') }} @if(setting('inventory.auto_reorder'))<span class="badge text-bg-success">{{ __('On') }}</span>@else<span class="badge text-bg-secondary">{{ __('Off') }}</span>@endif</div>
        <div class="small text-body-secondary">{{ __('Every night, parts below their minimum or forecast cover become draft purchase orders to their default supplier (parts already on order are skipped). Orders stay drafts for your approval.') }}</div>
    </div>
    <form method="post" action="{{ route('inventory.analytics.action', 'settings') }}" class="d-flex flex-wrap gap-2 align-items-center">@csrf
        <select name="warehouse_id" class="form-select form-select-sm w-auto" aria-label="{{ __('Warehouse') }}">@foreach($warehouses as $id => $n)<option value="{{ $id }}" @selected($warehouseId === $id)>{{ $n }}</option>@endforeach</select>
        @can('settings.update')
            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" name="auto_reorder" value="1" id="ar" @checked(setting('inventory.auto_reorder'))><label class="form-check-label small" for="ar">{{ __('Enable') }}</label></div>
            <button class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button>
        @endcan
        @can('purchase_orders.create')<button formaction="{{ route('inventory.analytics.action', 'reorder') }}" class="btn btn-sm btn-primary"><i class="bi bi-lightning"></i> {{ __('Run now') }}</button>@endcan
    </form>
</div></div>

<ul class="nav nav-tabs">
    @foreach($tabs as $k => [$l, $ic])<li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="{{ route('inventory.analytics', ['tab' => $k]) }}"><i class="bi {{ $ic }}"></i> {{ $l }}</a></li>@endforeach
</ul>
<div class="card border-top-0 rounded-top-0">
@if($tab === 'abc')
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div class="small text-body-secondary flex-grow-1">{{ __('Consumption value of the last 12 months (quantity used × cost). A = the parts making 80 % of the value (count often, never run out), B = the next 15 %, C = the rest.') }}
                @if($lastAbc)<br>{{ __('Classes saved on the parts') }}: {{ \Illuminate\Support\Carbon::parse($lastAbc)->format('Y-m-d') }}@endif</div>
            @can('parts.update')<form method="post" action="{{ route('inventory.analytics.action', 'abc') }}">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat"></i> {{ __('Save classes on parts') }}</button></form>@endcan
        </div>
        <div class="row g-2 mb-3">
            @foreach($abc['summary'] as $c => $s)
                <div class="col-md-4"><div class="card border-{{ $cls[$c] }}"><div class="card-body py-2 d-flex align-items-center gap-3">
                    <span class="fs-2 fw-bold text-{{ $cls[$c] }}">{{ $c }}</span>
                    <div><div>{{ trans_choice('{0} no parts|{1} 1 part|[2,*] :n parts', $s['parts'], ['n' => $s['parts']]) }}</div><div class="small text-body-secondary">{{ money($s['value']) }} · {{ $s['share'] }}%</div></div>
                </div></div></div>
            @endforeach
        </div>
    </div>
    <div class="table-responsive"><table class="table table-sm table-erp align-middle mb-0">
        <thead><tr><th>{{ __('Class') }}</th><th>{{ __('Part') }}</th><th class="text-end">{{ __('Qty used') }}</th><th class="text-end">{{ __('Consumption value') }}</th><th class="text-end">{{ __('Cumulative %') }}</th></tr></thead>
        <tbody>
        @forelse(array_slice($abc['rows'], 0, 100, true) as $id => $r)
            @php $p = $abcParts->get($id); @endphp
            <tr><td><span class="badge text-bg-{{ $cls[$r['class']] }}">{{ $r['class'] }}</span></td><td>@if($p)<a href="{{ route('parts.show', $p) }}">{{ $p->label() }}</a>@endif</td>
                <td class="text-end">{{ qty($r['qty']) }}</td><td class="text-end font-monospace">{{ money($r['value'], false) }}</td><td class="text-end">{{ $r['cum'] }}%</td></tr>
        @empty<tr><td colspan="5"><div class="empty-state py-3"><i class="bi bi-inbox"></i>{{ __('No consumption in the last 12 months.') }}</div></td></tr>@endforelse
        </tbody>
    </table></div>
@elseif($tab === 'aging')
    <div class="card-body">
        <div class="row g-2 mb-3">
            @foreach(['0_90' => __('0–90 days'), '91_180' => __('91–180 days'), '181_365' => __('181–365 days'), '365_plus' => __('Over a year')] as $k => $l)
                @php $b = $aging['buckets'][$k]; $pct = $aging['total'] > 0 ? round($b['value'] * 100 / $aging['total']) : 0; @endphp
                <div class="col-6 col-lg-3"><div class="card h-100 {{ $k === '365_plus' && $b['value'] > 0 ? 'border-danger' : '' }}"><div class="card-body py-2">
                    <div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-5">{{ money($b['value']) }}</div>
                    <div class="progress mt-1" style="height:6px" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $l }}"><div class="progress-bar {{ $k === '365_plus' ? 'bg-danger' : ($k === '181_365' ? 'bg-warning' : '') }}" style="width:{{ $pct }}%"></div></div>
                    <div class="small text-body-secondary mt-1">{{ $pct }}% · {{ trans_choice('{0} no parts|{1} 1 part|[2,*] :n parts', $b['parts'], ['n' => $b['parts']]) }}</div>
                </div></div></div>
            @endforeach
        </div>
        <h6>{{ __('Oldest stock (over 6 months)') }}</h6>
    </div>
    <ul class="list-group list-group-flush">
        @forelse($aging['oldest'] as $o)<li class="list-group-item d-flex"><a href="{{ route('parts.show', $o['part']) }}">{{ $o['part']->label() }}</a><span class="ms-auto font-monospace">{{ money($o['value'], false) }}</span></li>
        @empty<li class="list-group-item text-body-secondary small">{{ __('No stock older than 6 months.') }}</li>@endforelse
    </ul>
@elseif($tab === 'dead')
    <div class="card-body pb-0">
        <form class="d-flex gap-2 align-items-center mb-2"><input type="hidden" name="tab" value="dead"><label class="small" for="dd">{{ __('No sale or issue for') }}</label>
            <select id="dd" name="days" class="form-select form-select-sm w-auto" onchange="this.form.submit()">@foreach([90, 180, 365] as $d)<option value="{{ $d }}" @selected((int) request('days', 180) === $d)>{{ $d }} {{ __('days') }}</option>@endforeach</select>
            <span class="ms-auto fw-semibold">{{ __('Tied-up value') }}: {{ money($dead->sum('value')) }}</span></form>
    </div>
    <div class="table-responsive"><table class="table table-sm table-erp align-middle mb-0">
        <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Warehouse') }}</th><th class="text-end">{{ __('On hand') }}</th><th class="text-end">{{ __('Value') }}</th><th>{{ __('Last movement') }}</th></tr></thead>
        <tbody>@forelse($dead as $d)
            <tr><td><a href="{{ route('parts.show', $d->id) }}">{{ $d->sku }} — {{ app()->getLocale() === 'ar' ? $d->name_ar : ($d->name_en ?: $d->name_ar) }}</a></td><td class="small">{{ $d->warehouse }}</td>
                <td class="text-end">{{ qty($d->qty_on_hand) }}</td><td class="text-end font-monospace">{{ money($d->value, false) }}</td><td class="small">{{ $d->last_movement_at ? substr($d->last_movement_at, 0, 10) : '—' }}</td></tr>
        @empty<tr><td colspan="5"><div class="empty-state py-3"><i class="bi bi-check2-circle"></i>{{ __('No dead stock.') }}</div></td></tr>@endforelse</tbody>
    </table></div>
@elseif($tab === 'forecast')
    <div class="card-body small text-body-secondary pb-0">{{ __('Next-month demand from the last 6 months of sales and job issues (double exponential smoothing). Cover = months the current stock lasts.') }}</div>
    <div class="table-responsive"><table class="table table-sm table-erp align-middle mb-0">
        <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Last 6 months') }}</th><th class="text-end">{{ __('Forecast next month') }}</th><th class="text-end">{{ __('On hand') }}</th><th class="text-end">{{ __('Cover (months)') }}</th></tr></thead>
        <tbody>@forelse($forecasts as $f)
            @php $max = max(1, max($f['history'])); @endphp
            <tr><td><a href="{{ route('parts.show', $f['part']) }}">{{ $f['part']->label() }}</a></td>
                <td><div class="d-flex align-items-end gap-1" style="height:28px" aria-label="{{ implode(', ', $f['history']) }}">@foreach($f['history'] as $m => $v)<span title="{{ $m }}: {{ $v }}" style="width:9px;height:{{ max(2, round($v * 28 / $max)) }}px;background:var(--brand);opacity:.75;border-radius:2px"></span>@endforeach</div></td>
                <td class="text-end fw-semibold">{{ qty($f['forecast']) }}</td><td class="text-end">{{ qty($f['on_hand']) }}</td>
                <td class="text-end {{ $f['cover'] !== null && $f['cover'] < 1 ? 'text-danger fw-semibold' : '' }}">{{ $f['cover'] ?? '—' }}</td></tr>
        @empty<tr><td colspan="5"><div class="empty-state py-3"><i class="bi bi-graph-up"></i>{{ __('Not enough sales history yet.') }}</div></td></tr>@endforelse</tbody>
    </table></div>
@else
    <div class="card-body">
        <div class="small text-body-secondary mb-2">{{ __('Count a few parts often instead of everything once a year: class A every :a days, B every :b days, C every :c days.', ['a' => 30, 'b' => 90, 'c' => 365]) }}</div>
        <form method="post" action="{{ route('inventory.analytics.action', 'cycle') }}" class="d-flex flex-wrap gap-2 align-items-center">@csrf
            <select name="warehouse_id" class="form-select form-select-sm w-auto" aria-label="{{ __('Warehouse') }}">@foreach($warehouses as $id => $n)<option value="{{ $id }}" @selected($warehouseId === $id)>{{ $n }}</option>@endforeach</select>
            <label class="small" for="cl">{{ __('Parts per count') }}</label><input id="cl" type="number" name="limit" value="50" min="5" max="500" class="form-control form-control-sm" style="width:90px">
            @can('stock_counts.create')<button class="btn btn-sm btn-primary"><i class="bi bi-clipboard-check"></i> {{ __('Create cycle count') }}</button>@endcan
            <span class="ms-auto badge text-bg-warning fs-6">{{ trans_choice('{0} no parts due|{1} 1 part due|[2,*] :n parts due', $due->count(), ['n' => $due->count()]) }}</span>
        </form>
    </div>
    <div class="table-responsive"><table class="table table-sm table-erp align-middle mb-0">
        <thead><tr><th>{{ __('Class') }}</th><th>{{ __('Part') }}</th><th class="text-end">{{ __('On hand') }}</th><th>{{ __('Last counted') }}</th></tr></thead>
        <tbody>@forelse($due->take(100) as $l)
            <tr><td>@if($l->part?->abc_class)<span class="badge text-bg-{{ $cls[$l->part->abc_class] }}">{{ $l->part->abc_class }}</span>@else — @endif</td><td>{{ $l->part?->label() }}</td><td class="text-end">{{ qty($l->qty_on_hand) }}</td><td class="small">{{ $l->last_counted_at?->format('Y-m-d') ?? __('Never') }}</td></tr>
        @empty<tr><td colspan="4"><div class="empty-state py-3"><i class="bi bi-check2-all"></i>{{ __('No parts are due for counting in this warehouse.') }}</div></td></tr>@endforelse</tbody>
    </table></div>
@endif
</div>
@endsection
