@extends('layouts.app')
@section('title', __('New landed cost'))
@section('content')
<x-page-header :title="__('Landed cost (freight, customs, clearance)')" icon="bi-airplane" :breadcrumbs="[__('Landed Costs') => route('crud.index', 'landed-costs'), __('New')]"/>
<form method="post" action="{{ route('landed-costs.store') }}" class="card" x-data="{ lines: [{type:'freight', d:'', a:''}] }">@csrf <x-idem/>
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label required">{{ __('Goods receipt') }}</label><select name="goods_receipt_id" class="form-select" required>@foreach($receipts as $g)<option value="{{ $g->id }}" @selected($grnId == $g->id)>{{ $g->number }} — {{ $g->supplier?->name }} — {{ money($g->total) }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">{{ __('Cost supplier (optional)') }}</label><x-lookup name="supplier_id" type="suppliers"/></div>
        <div class="col-md-2"><label class="form-label">{{ __('Allocation') }}</label><select name="allocation_method" class="form-select"><option value="value">{{ __('By value') }}</option><option value="qty">{{ __('By quantity') }}</option></select></div>
        <div class="col-md-3"><label class="form-label">{{ __('Date') }}</label><input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}"></div>
        <div class="col-12">
            <template x-for="(l, i) in lines" :key="i"><div class="input-group mb-1">
                <select class="form-select" style="max-width:160px" :name="`lines[${i}][type]`" x-model="l.type"><option value="freight">{{ __('Freight') }}</option><option value="customs">{{ __('Customs') }}</option><option value="insurance">{{ __('Insurance') }}</option><option value="clearance">{{ __('Clearance') }}</option><option value="other">{{ __('Other') }}</option></select>
                <input class="form-control" :name="`lines[${i}][description]`" x-model="l.d" placeholder="{{ __('Description') }}">
                <input type="number" step="0.001" min="0" class="form-control" style="max-width:160px" :name="`lines[${i}][amount]`" x-model="l.a" placeholder="{{ __('Amount') }}">
                <button type="button" class="btn btn-outline-danger" @click="lines.splice(i,1)"><i class="bi bi-x"></i></button>
            </div></template>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="lines.push({type:'other', d:'', a:''})">+ {{ __('Add cost') }}</button>
        </div>
        <div class="col-12"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Save') }}</button></div>
</form>
@endsection
