@extends('layouts.app')
@section('title', __('Opening Balances'))
@section('content')
<x-page-header :title="__('Opening Balances')" icon="bi-door-open" :breadcrumbs="[__('Accounting'), __('Opening Balances')]" :subtitle="__('Any difference is posted to Opening Balance Equity. Inventory opening quantities are entered via a stock adjustment with reason Opening.')"/>
<form method="post" class="row g-3" x-data="{ cust: [{id:'', amount:''}], sup: [{id:'', amount:''}] }">@csrf <x-idem/>
    <div class="col-12"><div class="card"><div class="card-body"><label class="form-label">{{ __('Opening date') }}</label><input type="date" name="date" value="{{ now()->startOfYear()->toDateString() }}" class="form-control w-auto" required></div></div></div>
    <div class="col-xl-6"><div class="card"><div class="card-header">{{ __('Account balances') }}</div><div class="table-responsive" style="max-height:60vh"><table class="table table-sm mb-0">
        <thead class="table-light sticky-top"><tr><th>{{ __('Account') }}</th><th style="width:130px">{{ __('Debit') }}</th><th style="width:130px">{{ __('Credit') }}</th></tr></thead>
        <tbody>@foreach($accounts as $a)<tr><td class="small">{{ $a->label() }}</td><td><input type="number" step="0.001" min="0" name="accounts[{{ $a->id }}][debit]" class="form-control form-control-sm"></td><td><input type="number" step="0.001" min="0" name="accounts[{{ $a->id }}][credit]" class="form-control form-control-sm"></td></tr>@endforeach</tbody>
    </table></div></div></div>
    <div class="col-xl-6">
        <div class="card mb-3"><div class="card-header">{{ __('Customer balances (receivable)') }}</div><div class="card-body">
            <template x-for="(c, i) in cust" :key="i"><div class="row g-1 mb-1"><div class="col-8"><div class="lookup" data-lookup="customers"><input type="hidden" :name="`customers[${i}][id]`"><input type="text" class="form-control form-control-sm" autocomplete="off"><div class="lookup-menu"></div></div></div>
                <div class="col-4"><input type="number" step="0.001" :name="`customers[${i}][amount]`" class="form-control form-control-sm" placeholder="{{ __('Amount') }}"></div></div></template>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="cust.push({id:'', amount:''}); $nextTick(() => erpInitLookups())">+</button>
        </div></div>
        <div class="card"><div class="card-header">{{ __('Supplier balances (payable)') }}</div><div class="card-body">
            <template x-for="(c, i) in sup" :key="i"><div class="row g-1 mb-1"><div class="col-8"><div class="lookup" data-lookup="suppliers"><input type="hidden" :name="`suppliers[${i}][id]`"><input type="text" class="form-control form-control-sm" autocomplete="off"><div class="lookup-menu"></div></div></div>
                <div class="col-4"><input type="number" step="0.001" :name="`suppliers[${i}][amount]`" class="form-control form-control-sm" placeholder="{{ __('Amount') }}"></div></div></template>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="sup.push({id:'', amount:''}); $nextTick(() => erpInitLookups())">+</button>
        </div></div>
    </div>
    <div class="col-12 text-end"><button class="btn btn-primary btn-lg" data-confirm="{{ __('Post opening balances?') }}">{{ __('Post opening balances') }}</button></div>
</form>
@endsection
