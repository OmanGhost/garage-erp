@extends('layouts.app')
@section('title', $a->name)
@section('content')
<x-page-header :title="$a->code.' — '.$a->name" icon="bi-building-gear" :breadcrumbs="[__('Fixed Assets') => route('crud.index', 'fixed-assets'), $a->code]" :subtitle="status_badge($a->status)">
    @can('fixed_assets.update')<a href="{{ route('crud.edit', ['fixed-assets', $a->id]) }}" class="btn btn-outline-primary">{{ __('Edit') }}</a>@endcan
</x-page-header>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body small">
            <div><b>{{ __('Cost') }}:</b> {{ money($a->cost) }}</div><div><b>{{ __('Salvage') }}:</b> {{ money($a->salvage_value) }}</div>
            <div><b>{{ __('Life') }}:</b> {{ $a->useful_life_months }} {{ __('months') }} · {{ status_label($a->method) }}</div>
            <div><b>{{ __('Accumulated') }}:</b> {{ money($a->accumulated_depreciation) }}</div><div class="fs-5"><b>{{ __('Book value') }}:</b> {{ money($a->book_value) }}</div>
        </div></div>
        @can('fixed_assets.approve')
        @unless($acquired)
        <form method="post" action="{{ route('accounting.assets.acquire', $a) }}" class="card mb-3">@csrf <x-idem/><div class="card-header">{{ __('Post acquisition') }}</div><div class="card-body">
            <select name="bank_account_id" class="form-select form-select-sm mb-2"><option value="">{{ __('Paid on supplier credit / opening') }}</option>@foreach($banks as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
            <x-lookup name="supplier_id" type="suppliers" size="sm" :placeholder="__('Supplier (if on credit)')"/>
            <button class="btn btn-sm btn-primary w-100 mt-2">{{ __('Post') }}</button></div></form>
        @endunless
        @if($a->status !== 'disposed')
        <form method="post" action="{{ route('accounting.assets.dispose', $a) }}" class="card" data-confirm="{{ __('Dispose this asset?') }}">@csrf <x-idem/><div class="card-header">{{ __('Dispose / sell') }}</div><div class="card-body">
            <input type="number" step="0.001" min="0" name="proceeds" value="0" class="form-control form-control-sm mb-2" placeholder="{{ __('Proceeds') }}">
            <select name="bank_account_id" class="form-select form-select-sm mb-2"><option value="">—</option>@foreach($banks as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
            <button class="btn btn-sm btn-outline-danger w-100">{{ __('Dispose') }}</button></div></form>
        @endif
        @endcan
    </div>
    <div class="col-lg-8"><div class="card"><div class="card-header">{{ __('Depreciation schedule (posted)') }}</div><table class="table table-sm table-erp mb-0">
        <thead><tr><th>{{ __('Period') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Journal') }}</th></tr></thead>
        <tbody>@forelse($a->depreciations->sortBy('period_date') as $d)<tr><td>{{ $d->period_date->format('Y-m') }}</td><td class="text-end font-monospace">{{ money($d->amount, false) }}</td><td>@if($d->journal_entry_id)<a href="{{ route('journal.show', $d->journal_entry_id) }}">#{{ $d->journal_entry_id }}</a>@endif</td></tr>
        @empty<tr><td colspan="3" class="text-body-secondary">—</td></tr>@endforelse</tbody>
    </table></div></div>
</div>
@endsection
