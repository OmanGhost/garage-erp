@extends('layouts.app')
@section('title', __('Bank reconciliation'))
@section('content')
<x-page-header :title="__('Bank reconciliation').' — '.$rec->bankAccount->name" icon="bi-check2-square" :breadcrumbs="[__('Bank Reconciliations') => route('crud.index', 'bank-reconciliations'), $rec->statement_date->format('Y-m-d')]"
    :subtitle="__('Statement balance').' '.money($rec->statement_balance).' · '.__('Book balance').' '.money($book).' · '.__('Difference').' '.money($rec->difference).' '.status_badge($rec->status)"/>
<form method="post" class="card">@csrf @method('put')
    <div class="table-responsive"><table class="table table-sm table-erp mb-0">
        <thead><tr><th style="width:40px"><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.rl').forEach(c => c.checked = this.checked)"></th><th>{{ __('Date') }}</th><th>{{ __('Entry') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
        <tbody>@foreach($lines as $l)<tr><td><input type="checkbox" class="form-check-input rl" name="lines[]" value="{{ $l->id }}" @checked($l->bank_reconciliation_id === $rec->id)></td><td>{{ $l->entry->date->format('Y-m-d') }}</td><td>{{ $l->entry->number }}</td><td class="small">{{ $l->description ?: $l->entry->description }}</td>
            <td class="text-end font-monospace">{{ (float) $l->debit ? money($l->debit, false) : '' }}</td><td class="text-end font-monospace">{{ (float) $l->credit ? money($l->credit, false) : '' }}</td></tr>@endforeach</tbody>
    </table></div>
    <div class="card-footer text-end"><span class="small text-body-secondary me-2">{{ __('Tick the transactions that appear on the bank statement.') }}</span><button class="btn btn-primary">{{ __('Save reconciliation') }}</button></div>
</form>
@endsection
