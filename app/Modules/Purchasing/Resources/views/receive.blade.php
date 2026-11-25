@extends('layouts.app')
@section('title', __('Receive goods'))
@section('content')
<x-page-header :title="__('Receive goods for').' '.$po->number" icon="bi-box-seam" :breadcrumbs="[__('Purchase Orders') => route('crud.index', 'purchase-orders'), $po->number => route('purchase-orders.show', $po), __('Receive')]" :subtitle="e($po->supplier->name)"/>
<form method="post" class="card">@csrf <x-idem/>
    <div class="card-body row g-2">
        <div class="col-md-4"><label class="form-label">{{ __('Warehouse') }}</label><select name="warehouse_id" class="form-select">@foreach($warehouses as $id => $n)<option value="{{ $id }}" @selected($po->warehouse_id === $id)>{{ $n }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">{{ __('Supplier delivery note / ref.') }}</label><input name="supplier_reference" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
    </div>
    <div class="table-responsive"><table class="table table-erp mb-0 align-middle">
        <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Open qty') }}</th><th style="width:110px">{{ __('Receive') }}</th><th style="width:120px">{{ __('Unit cost') }}</th><th>{{ __('Batch') }}</th><th>{{ __('Expiry') }}</th><th>{{ __('Serial numbers') }}</th></tr></thead>
        <tbody>@foreach($po->items as $i)@php $open = (float) $i->qty - (float) $i->received_qty; @endphp
            @if($open > 0)
            <tr><td>{{ $i->part->label() }}</td><td>{{ qty($open) }}</td>
                <td><input type="number" step="0.001" min="0" max="{{ $open }}" name="lines[{{ $i->id }}][qty]" value="{{ $open }}" class="form-control form-control-sm"></td>
                <td><input type="number" step="0.0001" min="0" name="lines[{{ $i->id }}][unit_cost]" value="{{ round(((float) $i->qty * (float) $i->unit_cost - (float) $i->discount) / max((float) $i->qty, 0.001), 4) }}" class="form-control form-control-sm"></td>
                <td><input name="lines[{{ $i->id }}][batch_number]" class="form-control form-control-sm" @if(!$i->part->track_batch) placeholder="—" @endif></td>
                <td><input type="date" name="lines[{{ $i->id }}][expiry_date]" class="form-control form-control-sm" @if(!$i->part->track_expiry) disabled @endif></td>
                <td>@if($i->part->track_serial)<textarea name="lines[{{ $i->id }}][serials]" rows="1" class="form-control form-control-sm" placeholder="{{ __('One per line / comma') }}" required></textarea>@else<span class="small text-body-tertiary">—</span>@endif</td></tr>
            @endif
        @endforeach</tbody>
    </table></div>
    <div class="card-footer text-end"><button class="btn btn-success btn-lg"><i class="bi bi-check2-circle"></i> {{ __('Post goods receipt') }}</button></div>
</form>
@endsection
