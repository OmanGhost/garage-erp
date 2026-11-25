@extends('layouts.app')
@section('title', $title)
@section('content')
<x-page-header :title="$title" icon="bi-file-earmark-bar-graph" :breadcrumbs="[__('Reports Center') => route('reports.index'), $title]">
    <div class="btn-group">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel text-success"></i> Excel</a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary">CSV</a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf text-danger"></i> PDF</a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'print']) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i></a>
    </div>
</x-page-header>
<form class="card mb-3"><div class="card-body row g-2 align-items-end">
    <div class="col-auto"><label class="form-label small">{{ __('From') }}</label><input type="date" name="from" value="{{ $f['from'] }}" class="form-control"></div>
    <div class="col-auto"><label class="form-label small">{{ __('To') }}</label><input type="date" name="to" value="{{ $f['to'] }}" class="form-control"></div>
    <div class="col-auto"><label class="form-label small">{{ __('Branch') }}</label><select name="branch_id" class="form-select"><option value="">{{ __('All') }}</option>@foreach($branches as $id => $n)<option value="{{ $id }}" @selected(($f['branch_id'] ?? '') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="col-auto"><label class="form-label small">{{ __('Department') }}</label><select name="department_id" class="form-select"><option value="">{{ __('All') }}</option>@foreach($departments as $id => $n)<option value="{{ $id }}" @selected(($f['department_id'] ?? '') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="col-auto"><label class="form-label small">{{ __('Employee') }}</label><select name="user_id" class="form-select"><option value="">{{ __('All') }}</option>@foreach($users as $id => $n)<option value="{{ $id }}" @selected(($f['user_id'] ?? '') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small">{{ __('Customer') }}</label><x-lookup name="customer_id" type="customers" :value="$f['customer_id'] ?? null"/></div>
    <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-funnel"></i> {{ __('Run') }}</button></div>
</div></form>
@if($report['note'])<div class="alert alert-info">{{ $report['note'] }}</div>@endif
<div class="card"><div class="table-responsive"><table class="table table-sm table-erp table-hover mb-0">
    <thead><tr>@foreach($report['columns'] as $k => $l)<th class="{{ in_array($k, $report['money']) ? 'text-end' : '' }}">{{ $l }}</th>@endforeach</tr></thead>
    <tbody>@forelse($report['rows'] as $r)<tr>@foreach($report['columns'] as $k => $l)
        <td class="{{ in_array($k, $report['money']) ? 'text-end font-monospace' : '' }}">@if(in_array($k, $report['money'])){{ money($r[$k] ?? 0, false) }}@elseif($k === 'status' || $k === 'type'){!! status_badge((string) ($r[$k] ?? '')) !!}@else{{ $r[$k] ?? '' }}@endif</td>
    @endforeach</tr>@empty<tr><td colspan="{{ count($report['columns']) }}"><div class="empty-state"><i class="bi bi-inbox"></i>{{ __('No data for the selected filters.') }}</div></td></tr>@endforelse</tbody>
    @if($report['totals'])<tfoot><tr class="fw-bold">@foreach($report['columns'] as $k => $l)<td class="{{ in_array($k, $report['money']) ? 'text-end font-monospace' : '' }}">{{ $loop->first ? __('Total').' ('.count($report['rows']).')' : (isset($report['totals'][$k]) ? money($report['totals'][$k], false) : '') }}</td>@endforeach</tr></tfoot>@endif
</table></div></div>
@endsection
