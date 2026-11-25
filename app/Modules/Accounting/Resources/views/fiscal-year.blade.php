@extends('layouts.app')
@section('title', __('Fiscal year').' '.$y->name)
@section('content')
<x-page-header :title="__('Fiscal year').' '.$y->name" icon="bi-calendar3" :breadcrumbs="[__('Fiscal Years') => route('crud.index', 'fiscal-years'), $y->name]" :subtitle="$y->start_date->format('Y-m-d').' → '.$y->end_date->format('Y-m-d').' · '.status_badge($y->status)">
    @if($y->status === 'open')@can('fiscal_years.close_period')<form method="post" action="{{ route('accounting.fiscal.close', $y) }}" data-confirm="{{ __('Close the fiscal year? Net income will be transferred to retained earnings and all periods locked.') }}">@csrf <x-idem/><button class="btn btn-danger"><i class="bi bi-lock"></i> {{ __('Close fiscal year') }}</button></form>@endcan @endif
</x-page-header>
<div class="card"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('Period') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
    <tbody>@foreach($y->periods->sortBy('start_date') as $p)<tr><td>{{ $p->name }}</td><td>{{ $p->start_date->format('Y-m-d') }}</td><td>{{ $p->end_date->format('Y-m-d') }}</td><td>{!! status_badge($p->status) !!}</td>
        <td class="text-end">@can('fiscal_years.close_period')@if($y->status === 'open')
            @if($p->status === 'open')<form method="post" action="{{ route('accounting.fiscal.period', [$p, 'close']) }}" data-confirm="{{ __('Close this period? No more postings will be allowed in it.') }}">@csrf <x-idem/><button class="btn btn-sm btn-outline-danger">{{ __('Close') }}</button></form>
            @else<form method="post" action="{{ route('accounting.fiscal.period', [$p, 'reopen']) }}">@csrf <x-idem/><button class="btn btn-sm btn-outline-secondary">{{ __('Reopen') }}</button></form>@endif
        @endif @endcan</td></tr>@endforeach</tbody>
</table></div>
@endsection
