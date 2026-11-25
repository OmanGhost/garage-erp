@extends('layouts.app')
@section('title', __('Run depreciation'))
@section('content')
<x-page-header :title="__('Run monthly depreciation')" icon="bi-calculator" :breadcrumbs="[__('Fixed Assets') => route('crud.index', 'fixed-assets'), __('Depreciation')]"/>
<form method="post" class="card" style="max-width:480px">@csrf <x-idem/><div class="card-body">
    <label class="form-label">{{ __('Month') }}</label><input type="month" name="month" value="{{ now()->format('Y-m') }}" class="form-control mb-3" required>
    <p class="small text-body-secondary">{{ __('Posts one depreciation entry per active asset for the month. Already-depreciated months are skipped automatically.') }}</p>
    <button class="btn btn-primary w-100">{{ __('Post depreciation') }}</button>
</div></form>
@endsection
