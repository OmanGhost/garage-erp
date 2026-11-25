@extends('layouts.app')
@section('title', $rfq->number)
@section('content')
<x-page-header :title="__('RFQ').' '.$rfq->number" icon="bi-envelope-paper" :breadcrumbs="[__('RFQs') => route('crud.index', 'rfqs'), $rfq->number]" :subtitle="status_badge($rfq->status).' · '.__('Due').' '.($rfq->due_date?->format('Y-m-d') ?? '—')"/>
<div class="card mb-3"><div class="card-header"><i class="bi bi-table me-1"></i>{{ __('Quote comparison') }} <span class="small text-body-secondary">— {{ __('lowest price highlighted') }}</span></div>
    <div class="table-responsive"><table class="table table-erp mb-0 align-middle">
        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Qty') }}</th>@foreach($rfq->suppliers as $rs)<th class="text-end">{{ $rs->supplier->name }} {!! status_badge($rs->status) !!}</th>@endforeach</tr></thead>
        <tbody>@foreach($matrix as $row)
            <tr><td>{{ $row['item']->description }}</td><td>{{ qty($row['item']->qty) }}</td>
                @foreach($rfq->suppliers as $rs)<td class="text-end font-monospace {{ $row['best'] === $rs->id ? 'table-success fw-bold' : '' }}">{{ $row['prices'][$rs->id] !== null ? money($row['prices'][$rs->id], false) : '—' }}</td>@endforeach</tr>
        @endforeach</tbody>
        <tfoot><tr class="fw-bold"><td colspan="2">{{ __('Total') }} / {{ __('Delivery days') }}</td>@foreach($rfq->suppliers as $rs)<td class="text-end font-monospace">{{ money($rs->total, false) }}<div class="small fw-normal">{{ $rs->delivery_days ? $rs->delivery_days.' '.__('days') : '' }}</div></td>@endforeach</tr>
        @if($rfq->status !== 'awarded')
        <tr><td colspan="2"></td>@foreach($rfq->suppliers as $rs)<td class="text-end">@if($rs->status === 'quoted')@can('purchase_orders.create')
            <form method="post" action="{{ route('rfqs.award', [$rfq, $rs]) }}" class="d-flex gap-1 justify-content-end">@csrf <x-idem/><select name="warehouse_id" class="form-select form-select-sm" style="max-width:130px">@foreach($warehouses as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select><button class="btn btn-sm btn-success">{{ __('Award') }}</button></form>
        @endcan @endif</td>@endforeach</tr>
        @endif
        </tfoot>
    </table></div></div>
@if($rfq->status !== 'awarded')
<div class="row g-3">
    @foreach($rfq->suppliers as $rs)
    <div class="col-lg-6"><form method="post" action="{{ route('rfqs.quote', $rs) }}" class="card">@csrf <x-idem/>
        <div class="card-header">{{ __('Enter quote') }} — {{ $rs->supplier->name }}</div>
        <table class="table table-sm mb-0">@foreach($rfq->items as $it)@php $q = $rs->quotes->firstWhere('rfq_item_id', $it->id); @endphp
            <tr><td>{{ $it->description }} × {{ qty($it->qty) }}</td><td style="width:130px"><input type="number" step="0.0001" min="0" name="prices[{{ $it->id }}][unit_price]" value="{{ $q ? (float) $q->unit_price : '' }}" class="form-control form-control-sm" placeholder="{{ __('Unit price') }}"></td>
                <td style="width:90px"><input type="number" min="0" name="prices[{{ $it->id }}][lead_days]" value="{{ $q?->lead_days }}" class="form-control form-control-sm" placeholder="{{ __('Days') }}"></td></tr>@endforeach</table>
        <div class="card-footer d-flex gap-2"><input type="number" name="delivery_days" min="0" value="{{ $rs->delivery_days }}" class="form-control form-control-sm" style="max-width:140px" placeholder="{{ __('Delivery days') }}"><button class="btn btn-sm btn-primary ms-auto">{{ __('Save quote') }}</button></div>
    </form></div>
    @endforeach
</div>
@endif
@endsection
