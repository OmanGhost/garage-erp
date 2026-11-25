@extends('layouts.app')
@section('title', $c->number)
@section('content')
<x-page-header :title="__('Stock count').' '.$c->number" icon="bi-clipboard-data" :breadcrumbs="[__('Stock Counts') => route('crud.index', 'stock-counts'), $c->number]"
    :subtitle="e($c->warehouse->displayName()).' · '.status_label($c->type).' · '.status_badge($c->status)">
    <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Count sheet') }}</button>
    @if($c->status !== 'posted')@can('stock_counts.approve')<form method="post" action="{{ route('inventory.counts.post', $c) }}" data-confirm="{{ __('Post the count and adjust stock?') }}">@csrf <x-idem/><button class="btn btn-success">{{ __('Post count') }}</button></form>@endcan @endif
</x-page-header>
<form method="post" action="{{ route('inventory.counts.update', $c) }}" class="card">@csrf @method('put')
    <div class="table-responsive"><table class="table table-sm table-erp mb-0 align-middle">
        <thead><tr><th>SKU</th><th>{{ __('Part') }}</th><th>{{ __('System qty') }}</th><th style="width:140px">{{ __('Counted') }}</th><th>{{ __('Difference') }}</th><th class="text-end">{{ __('Value diff.') }}</th></tr></thead>
        <tbody>@foreach($c->items as $i)
            <tr><td>{{ $i->part->sku }}</td><td>{{ $i->part->displayName() }}</td><td>{{ qty($i->system_qty) }}</td>
                <td>@if($c->status !== 'posted')<input type="number" step="0.001" min="0" name="counted[{{ $i->id }}]" value="{{ $i->counted_qty !== null ? (float) $i->counted_qty : '' }}" class="form-control form-control-sm" data-scan-target>@else {{ $i->counted_qty !== null ? qty($i->counted_qty) : '—' }} @endif</td>
                <td class="{{ (float) $i->difference < 0 ? 'text-danger' : ((float) $i->difference > 0 ? 'text-success' : '') }}">{{ $i->counted_qty !== null ? qty($i->difference) : '' }}</td>
                <td class="text-end font-monospace">{{ $i->counted_qty !== null ? money((float) $i->difference * (float) $i->unit_cost, false) : '' }}</td></tr>
        @endforeach</tbody>
    </table></div>
    @if($c->status !== 'posted')<div class="card-footer text-end"><button class="btn btn-primary">{{ __('Save counts') }}</button></div>@endif
</form>
@endsection
