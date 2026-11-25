@extends('layouts.app')
@section('title', $a->number)
@section('content')
<x-page-header :title="__('Stock adjustment').' '.$a->number" icon="bi-sliders" :breadcrumbs="[__('Stock Adjustments') => route('crud.index', 'stock-adjustments'), $a->number]"
    :subtitle="e($a->warehouse->displayName()).' · '.status_label($a->reason).' · '.status_badge($a->status)">
    @if($a->status === 'draft')@can('stock_adjustments.approve')<form method="post" action="{{ route('inventory.adjustments.post', $a) }}" data-confirm="{{ __('Post this adjustment?') }}">@csrf <x-idem/><button class="btn btn-success">{{ __('Post') }}</button></form>@endcan @endif
</x-page-header>
<div class="card"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Qty') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end">{{ __('Value') }}</th><th>{{ __('Notes') }}</th></tr></thead>
    <tbody>@foreach($a->items as $i)<tr><td>{{ $i->part->label() }}</td><td class="{{ (float) $i->qty < 0 ? 'text-danger' : 'text-success' }}">{{ qty($i->qty) }}</td><td class="text-end font-monospace">{{ money($i->unit_cost, false) }}</td><td class="text-end font-monospace">{{ money((float) $i->qty * (float) $i->unit_cost, false) }}</td><td>{{ $i->notes }}</td></tr>@endforeach</tbody>
</table><div class="card-body small text-body-secondary">{{ $a->notes }} · {{ $a->creator?->name }}</div></div>
@endsection
