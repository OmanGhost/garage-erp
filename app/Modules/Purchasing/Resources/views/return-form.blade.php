@extends('layouts.app')
@section('title', __('Purchase return'))
@section('content')
<x-page-header :title="__('Return goods to supplier')" icon="bi-arrow-return-left" :breadcrumbs="[__('Purchase Returns') => route('crud.index', 'purchase-returns'), __('New')]"/>
@if(! $grn)
    <div class="alert alert-info">{{ __('Open a goods receipt and choose "Return to supplier".') }} <a href="{{ route('crud.index', 'goods-receipts') }}">{{ __('Goods Receipts') }}</a></div>
@else
<form method="post" action="{{ route('purchase-returns.store') }}" class="card">@csrf <x-idem/>
    <input type="hidden" name="goods_receipt_id" value="{{ $grn->id }}">
    <div class="card-header">{{ $grn->number }} — {{ $grn->supplier->name }}</div>
    <table class="table table-erp mb-0">
        <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Received') }}</th><th>{{ __('Returned') }}</th><th style="width:140px">{{ __('Return now') }}</th></tr></thead>
        <tbody>@foreach($grn->items as $i)@php $max = (float) $i->qty - (float) $i->returned_qty; @endphp<tr><td>{{ $i->part->label() }}</td><td>{{ qty($i->qty) }}</td><td>{{ qty($i->returned_qty) }}</td>
            <td><input type="number" step="0.001" min="0" max="{{ $max }}" name="qty[{{ $i->id }}]" value="0" class="form-control form-control-sm" @disabled($max <= 0)></td></tr>@endforeach</tbody>
    </table>
    <div class="card-body"><label class="form-label required">{{ __('Reason') }}</label><input name="reason" class="form-control" required></div>
    <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Save') }}</button></div>
</form>
@endif
@endsection
