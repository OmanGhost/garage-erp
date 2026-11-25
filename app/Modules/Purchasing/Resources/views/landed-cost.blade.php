@extends('layouts.app')
@section('title', $lc->number)
@section('content')
<x-page-header :title="__('Landed cost').' '.$lc->number" icon="bi-airplane" :breadcrumbs="[__('Landed Costs') => route('crud.index', 'landed-costs'), $lc->number]"
    :subtitle="__('GRN').' <a href=\''.route('goods-receipts.show', $lc->receipt).'\'>'.e($lc->receipt->number).'</a> · '.status_badge($lc->status)">
    @if($lc->status === 'draft')@can('landed_costs.approve')<form method="post" action="{{ route('landed-costs.post', $lc) }}" data-confirm="{{ __('Allocate and post?') }}">@csrf <x-idem/><button class="btn btn-success">{{ __('Allocate & post') }}</button></form>@endcan @endif
</x-page-header>
<div class="row g-3">
    <div class="col-lg-5"><div class="card"><table class="table table-erp mb-0">
        @foreach($lc->lines as $l)<tr><td>{{ status_label($l->type) }}</td><td>{{ $l->description }}</td><td class="text-end font-monospace">{{ money($l->amount, false) }}</td></tr>@endforeach
        <tr class="fw-bold"><td colspan="2">{{ __('Total') }} ({{ $lc->allocation_method === 'qty' ? __('by quantity') : __('by value') }})</td><td class="text-end font-monospace">{{ money($lc->total) }}</td></tr>
    </table></div></div>
    <div class="col-lg-7"><div class="card"><div class="card-header">{{ __('Received items') }}</div><table class="table table-sm table-erp mb-0">
        <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Qty') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end">{{ __('Landed / unit') }}</th></tr></thead>
        <tbody>@foreach($lc->receipt->items as $i)<tr><td>{{ $i->part->label() }}</td><td>{{ qty($i->qty) }}</td><td class="text-end font-monospace">{{ money($i->unit_cost, false) }}</td><td class="text-end font-monospace">{{ money($i->landed_cost_per_unit, false) }}</td></tr>@endforeach</tbody>
    </table></div></div>
</div>
@endsection
