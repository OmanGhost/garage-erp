@extends('layouts.app')
@section('title', $e->number)
@section('content')
<x-page-header :title="__('Journal entry').' '.$e->number" icon="bi-journal-text" :breadcrumbs="[__('Journal Entries') => route('crud.index', 'journal-entries'), $e->number]"
    :subtitle="$e->date->format('Y-m-d').' · '.e($e->description).' · '.status_badge($e->status)">
    @if($e->status === 'posted')@can('journal_entries.reverse')
        <button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#rev"><i class="bi bi-arrow-counterclockwise"></i> {{ __('Reverse') }}</button>
    @endcan @endif
</x-page-header>
<div class="collapse mb-3" id="rev"><form method="post" action="{{ route('journal.reverse', $e) }}" class="card card-body row g-2 flex-row">@csrf <x-idem/>
    <div class="col-md-3"><input type="date" name="date" value="{{ now()->toDateString() }}" class="form-control"></div>
    <div class="col-md-7"><input name="reason" class="form-control" placeholder="{{ __('Reason') }}" required></div>
    <div class="col-md-2"><button class="btn btn-danger w-100">{{ __('Post reversal') }}</button></div>
</form></div>
<div class="card"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Party') }}</th><th>{{ __('Cost center') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
    <tbody>@foreach($e->lines as $l)<tr><td><a href="{{ route('accounting.ledger', ['account_id' => $l->account_id]) }}">{{ $l->account->label() }}</a></td><td class="small">{{ $l->party_type ? status_label($l->party_type).' #'.$l->party_id : '' }}</td><td class="small">{{ $l->costCenter?->displayName() }}</td><td class="small">{{ $l->description }}</td>
        <td class="text-end font-monospace">{{ (float) $l->debit ? money($l->debit, false) : '' }}</td><td class="text-end font-monospace">{{ (float) $l->credit ? money($l->credit, false) : '' }}</td></tr>@endforeach</tbody>
    <tfoot><tr class="fw-bold"><td colspan="4">{{ __('Total') }}</td><td class="text-end font-monospace">{{ money($e->total_debit, false) }}</td><td class="text-end font-monospace">{{ money($e->total_credit, false) }}</td></tr></tfoot>
</table>
<div class="card-body small text-body-secondary">
    {{ __('Source') }}: {{ $e->source_type ? status_label($e->source_type).' #'.$e->source_id : __('Manual') }} ({{ $e->purpose }}) ·
    {{ __('Posted by') }} {{ $e->poster?->name ?? __('System') }} {{ $e->posted_at?->format('Y-m-d H:i') }}
    @if($e->reversalOf) · {{ __('Reversal of') }} <a href="{{ route('journal.show', $e->reversalOf) }}">{{ $e->reversalOf->number }}</a>@endif
    @if($e->reversedBy) · {{ __('Reversed by') }} <a href="{{ route('journal.show', $e->reversedBy) }}">{{ $e->reversedBy->number }}</a>@endif
</div></div>
@endsection
