@extends('layouts.app')
@section('title', __('General ledger'))
@section('content')
<x-page-header :title="__('General ledger')" icon="bi-journal-text" :breadcrumbs="[__('Chart of Accounts') => route('crud.index', 'accounts'), __('Ledger')]">
    @if($account)<a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn btn-outline-secondary">Excel</a><a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" target="_blank" class="btn btn-outline-secondary">PDF</a>@endif
</x-page-header>
<form class="card mb-3"><div class="card-body row g-2 align-items-end">
    <div class="col-md-5"><label class="form-label small">{{ __('Account') }}</label><select name="account_id" class="form-select" required><option value="">—</option>@foreach($accounts as $id => $n)<option value="{{ $id }}" @selected($account?->id === $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="col-auto"><label class="form-label small">{{ __('From') }}</label><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
    <div class="col-auto"><label class="form-label small">{{ __('To') }}</label><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
    <div class="col-auto"><button class="btn btn-primary">{{ __('Show') }}</button></div>
</div></form>
@if($data)
<div class="card"><div class="table-responsive"><table class="table table-sm table-erp mb-0">
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Entry') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
    <tbody><tr class="table-light"><td colspan="5">{{ __('Opening balance') }}</td><td class="text-end font-monospace">{{ money($data['opening'], false) }}</td></tr>
    @foreach($data['rows'] as $r)<tr><td>{{ $r->date }}</td><td><a href="{{ route('journal.show', $r->entry_id) }}">{{ $r->number }}</a></td><td class="small">{{ $r->line_description ?: $r->description }}</td><td class="text-end font-monospace">{{ (float) $r->debit ? money($r->debit, false) : '' }}</td><td class="text-end font-monospace">{{ (float) $r->credit ? money($r->credit, false) : '' }}</td><td class="text-end font-monospace">{{ money($r->balance, false) }}</td></tr>@endforeach</tbody>
    <tfoot><tr class="fw-bold"><td colspan="5">{{ __('Closing balance') }}</td><td class="text-end font-monospace">{{ money($data['closing']) }}</td></tr></tfoot>
</table></div></div>
@endif
@endsection
