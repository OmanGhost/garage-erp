@extends('layouts.admin')
@section('title', __('Plans'))
@section('content')
<h4 class="mb-3">{{ __('Subscription plans') }}</h4>
<div class="row g-3">
    @foreach($plans->push(new \App\Modules\Core\Models\Plan(['is_active' => true, 'trial_days' => 14, 'modules' => array_keys(config('erp.modules'))])) as $p)
    <div class="col-lg-4"><form method="post" action="{{ route('admin.plans.save') }}" class="card h-100">@csrf
        <input type="hidden" name="id" value="{{ $p->id }}">
        <div class="card-header">{{ $p->id ? $p->name : __('New plan') }}</div>
        <div class="card-body row g-2">
            <div class="col-6"><label class="form-label small">{{ __('Code') }}</label><input name="code" value="{{ $p->code }}" class="form-control form-control-sm" required></div>
            <div class="col-6"><label class="form-label small">{{ __('Sort') }}</label><input name="sort" type="number" value="{{ $p->sort }}" class="form-control form-control-sm"></div>
            <div class="col-6"><label class="form-label small">{{ __('Name') }}</label><input name="name" value="{{ $p->name }}" class="form-control form-control-sm" required></div>
            <div class="col-6"><label class="form-label small">{{ __('Name (Arabic)') }}</label><input name="name_ar" value="{{ $p->name_ar }}" class="form-control form-control-sm"></div>
            <div class="col-4"><label class="form-label small">{{ __('Monthly') }}</label><input name="price_monthly" type="number" step="0.001" value="{{ (float) $p->price_monthly }}" class="form-control form-control-sm"></div>
            <div class="col-4"><label class="form-label small">{{ __('Yearly') }}</label><input name="price_yearly" type="number" step="0.001" value="{{ (float) $p->price_yearly }}" class="form-control form-control-sm"></div>
            <div class="col-4"><label class="form-label small">{{ __('Trial days') }}</label><input name="trial_days" type="number" value="{{ $p->trial_days }}" class="form-control form-control-sm"></div>
            <div class="col-3"><label class="form-label small">{{ __('Users') }}</label><input name="max_users" type="number" value="{{ $p->max_users }}" class="form-control form-control-sm"></div>
            <div class="col-3"><label class="form-label small">{{ __('Branches') }}</label><input name="max_branches" type="number" value="{{ $p->max_branches }}" class="form-control form-control-sm"></div>
            <div class="col-3"><label class="form-label small">{{ __('Warehouses') }}</label><input name="max_warehouses" type="number" value="{{ $p->max_warehouses }}" class="form-control form-control-sm"></div>
            <div class="col-3"><label class="form-label small">MB</label><input name="max_storage_mb" type="number" value="{{ $p->max_storage_mb }}" class="form-control form-control-sm"></div>
            <div class="col-12">@foreach(config('erp.modules') as $k => $m)<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="modules[]" value="{{ $k }}" id="p{{ $p->id }}{{ $k }}" @checked(in_array($k, $p->modules ?? []))><label class="form-check-label small" for="p{{ $p->id }}{{ $k }}">{{ __($m['label']) }}</label></div>@endforeach</div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($p->is_active)><label class="form-check-label">{{ __('Active') }}</label></div></div>
        </div>
        <div class="card-footer text-end"><button class="btn btn-sm btn-primary">{{ __('Save') }}</button></div>
    </form></div>
    @endforeach
</div>
@endsection
