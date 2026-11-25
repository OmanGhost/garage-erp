@extends('layouts.app')
@section('title', __('New stock adjustment'))
@section('content')
<x-page-header :title="__('New stock adjustment')" icon="bi-sliders" :breadcrumbs="[__('Stock Adjustments') => route('crud.index', 'stock-adjustments'), __('New')]"
    :subtitle="__('Damage / loss / expired quantities are deducted. Found / opening / correction use the sign you enter (+ in, − out).')"/>
<form method="post" action="{{ route('inventory.adjustments.store') }}" class="card">@csrf <x-idem/>
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">{{ __('Warehouse') }}</label><select name="warehouse_id" class="form-select">@foreach($warehouses as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">{{ __('Reason') }}</label><select name="reason" class="form-select">@foreach(['opening', 'damage', 'loss', 'found', 'correction', 'expired'] as $r)<option value="{{ $r }}">{{ status_label($r) }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">{{ __('Date') }}</label><input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}"></div>
        <div class="col-12"><x-qty-items :with-cost="true" :with-notes="true" :signed="true"/></div>
        <div class="col-12"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-outline-primary">{{ __('Save draft') }}</button> @can('stock_adjustments.approve')<button class="btn btn-primary" name="post_now" value="1">{{ __('Save & post') }}</button>@endcan</div>
</form>
@endsection
