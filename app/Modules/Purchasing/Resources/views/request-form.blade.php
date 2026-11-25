@extends('layouts.app')
@section('title', __('New purchase request'))
@section('content')
<x-page-header :title="__('New internal purchase request')" icon="bi-journal-plus" :breadcrumbs="[__('Purchase Requests') => route('crud.index', 'purchase-requests'), __('New')]"/>
<form method="post" action="{{ route('purchase-requests.store') }}" class="card">@csrf <x-idem/>
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label">{{ __('Required by') }}</label><input type="date" name="required_date" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">{{ __('Priority') }}</label><select name="priority" class="form-select">@foreach(\App\Support\Options::PRIORITIES as $p)<option value="{{ $p }}" @selected($p === 'normal')>{{ status_label($p) }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
        <div class="col-12"><x-line-items lookup="parts" price-field="purchase_price" :show-tax="false" :show-discount="false" :cost-label="__('Est. cost')"/></div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Submit request') }}</button></div>
</form>
@endsection
