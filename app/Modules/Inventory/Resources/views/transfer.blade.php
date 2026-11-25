@extends('layouts.app')
@section('title', $t->number)
@section('content')
<x-page-header :title="__('Stock transfer').' '.$t->number" icon="bi-truck" :breadcrumbs="[__('Stock Transfers') => route('crud.index', 'stock-transfers'), $t->number]"
    :subtitle="e($t->fromWarehouse->displayName()).' → '.e($t->toWarehouse->displayName()).' · '.status_badge($t->status)">
    @can('stock_transfers.update')
        @if($t->status === 'draft')
            <form method="post" action="{{ route('inventory.transfers.action', [$t, 'send']) }}">@csrf <x-idem/><button class="btn btn-primary"><i class="bi bi-send"></i> {{ __('Dispatch') }}</button></form>
            <form method="post" action="{{ route('inventory.transfers.action', [$t, 'cancel']) }}" data-confirm="{{ __('Cancel?') }}">@csrf <x-idem/><button class="btn btn-outline-danger">{{ __('Cancel') }}</button></form>
        @endif
    @endcan
</x-page-header>
<form method="post" action="{{ route('inventory.transfers.action', [$t, 'receive']) }}" class="card">@csrf <x-idem/>
    <table class="table table-erp mb-0">
        <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Qty sent') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th>{{ __('Received') }}</th></tr></thead>
        <tbody>@foreach($t->items as $i)<tr><td>{{ $i->part->label() }}</td><td>{{ qty($i->qty) }}</td><td class="text-end font-monospace">{{ money($i->unit_cost, false) }}</td>
            <td style="width:150px">@if($t->status === 'in_transit')<input type="number" step="0.001" min="0" max="{{ (float) $i->qty }}" name="received[{{ $i->id }}]" value="{{ (float) $i->qty }}" class="form-control form-control-sm">@else {{ qty($i->received_qty) }} @endif</td></tr>@endforeach</tbody>
    </table>
    @if($t->status === 'in_transit')<div class="card-footer text-end">@can('stock_transfers.update')<button class="btn btn-success"><i class="bi bi-box-arrow-in-down"></i> {{ __('Receive at destination') }}</button>@endcan
        <div class="small text-body-secondary mt-1">{{ __('Any shortage is posted as inventory loss.') }}</div></div>@endif
</form>
@endsection
