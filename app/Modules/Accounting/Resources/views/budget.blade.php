@extends('layouts.app')
@section('title', $b->name)
@section('content')
<x-page-header :title="__('Budget').' — '.$b->name" icon="bi-piggy-bank" :breadcrumbs="[__('Budgets') => route('crud.index', 'budgets'), $b->name]" :subtitle="__('Fiscal year').' '.$b->fiscalYear->name"/>
<div class="card mb-3"><div class="card-header">{{ __('Budget vs actual') }}</div><table class="table table-sm table-erp mb-0">
    <thead><tr><th>{{ __('Account') }}</th><th class="text-end">{{ __('Budget') }}</th><th class="text-end">{{ __('Actual') }}</th><th class="text-end">{{ __('Variance') }}</th></tr></thead>
    <tbody>@forelse($compare as $r)<tr><td>{{ $r['account'] }}</td><td class="text-end font-monospace">{{ money($r['budget'], false) }}</td><td class="text-end font-monospace">{{ money($r['actual'], false) }}</td>
        <td class="text-end font-monospace {{ ($r['type'] === 'expense' ? -1 : 1) * (float) $r['variance'] >= 0 ? 'text-success' : 'text-danger' }}">{{ money($r['variance'], false) }}</td></tr>
    @empty<tr><td colspan="4" class="text-body-secondary">{{ __('Enter monthly amounts below.') }}</td></tr>@endforelse</tbody>
</table></div>
<form method="post" class="card">@csrf @method('put')
    <div class="table-responsive" style="max-height:65vh"><table class="table table-sm mb-0">
        <thead class="table-light sticky-top"><tr><th>{{ __('Account') }}</th>@for($m = 1; $m <= 12; $m++)<th style="min-width:90px">{{ $m }}</th>@endfor</tr></thead>
        <tbody>@foreach($accounts as $a)<tr><td class="small text-nowrap">{{ $a->label() }}</td>@for($m = 1; $m <= 12; $m++)<td><input type="number" step="0.001" min="0" name="amounts[{{ $a->id }}][{{ $m }}]" value="{{ isset($lines[$a->id][$m]) ? (float) $lines[$a->id][$m]->amount : '' }}" class="form-control form-control-sm"></td>@endfor</tr>@endforeach</tbody>
    </table></div>
    <div class="card-footer text-end">@can('budgets.update')<button class="btn btn-primary">{{ __('Save budget') }}</button>@endcan</div>
</form>
@endsection
