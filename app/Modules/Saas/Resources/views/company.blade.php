@extends('layouts.admin')
@section('title', $c->displayName())
@section('content')
<div class="d-flex mb-3 gap-2"><h4 class="mb-0">{{ $c->displayName() }}</h4>{!! status_badge($c->subscription_status) !!}
    <form method="post" action="{{ route('admin.companies.enter', $c) }}" class="ms-auto">@csrf<button class="btn btn-outline-primary"><i class="bi bi-box-arrow-in-right"></i> {{ __('Enter tenant') }}</button></form></div>
<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" action="{{ route('admin.companies.update', $c) }}" class="card mb-3">@csrf @method('put')
            <div class="card-header">{{ __('Subscription & modules') }}</div>
            <div class="card-body row g-2">
                <div class="col-md-4"><label class="form-label">{{ __('Plan') }}</label><select name="plan_id" class="form-select">@foreach($plans as $p)<option value="{{ $p->id }}" @selected($c->plan_id === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">{{ __('Status') }}</label><select name="subscription_status" class="form-select">@foreach(['trial', 'active', 'past_due', 'suspended', 'cancelled'] as $s)<option value="{{ $s }}" @selected($c->subscription_status === $s)>{{ status_label($s) }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">{{ __('Ends at') }}</label><input type="date" name="subscription_ends_at" value="{{ $c->subscription_ends_at?->toDateString() }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">{{ __('Trial ends') }}</label><input type="date" name="trial_ends_at" value="{{ $c->trial_ends_at?->toDateString() }}" class="form-control"></div>
                <div class="col-md-8"><label class="form-label">{{ __('Custom domain') }}</label><input name="custom_domain" value="{{ $c->custom_domain }}" class="form-control" dir="ltr" placeholder="erp.mygarage.om"></div>
                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" @checked($c->is_active)><label class="form-check-label" for="ia">{{ __('Tenant active') }}</label></div></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="override_modules" value="1" id="om" @checked(is_array($c->enabled_modules))><label class="form-check-label" for="om">{{ __('Override plan modules for this tenant') }}</label></div>
                    <div class="row mt-1">@foreach(config('erp.modules') as $k => $m)<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="modules[]" value="{{ $k }}" id="m{{ $k }}" @checked($c->moduleEnabled($k)) @disabled(!empty($m['core']))><label class="form-check-label small" for="m{{ $k }}">{{ __($m['label']) }}</label></div></div>@endforeach</div></div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Save') }}</button></div>
        </form>
        <form method="post" action="{{ route('admin.companies.renew', $c) }}" class="card">@csrf <x-idem/>
            <div class="card-header">{{ __('Renew subscription') }}</div>
            <div class="card-body row g-2 align-items-end">
                <div class="col-md-4"><select name="plan_id" class="form-select">@foreach($plans as $p)<option value="{{ $p->id }}" @selected($c->plan_id === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><select name="billing_cycle" class="form-select"><option value="monthly">{{ __('Monthly') }}</option><option value="yearly">{{ __('Yearly') }}</option></select></div>
                <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="paid" value="1" id="pd"><label class="form-check-label" for="pd">{{ __('Paid') }}</label></div></div>
                <div class="col-md-3"><button class="btn btn-success w-100">{{ __('Renew') }}</button></div>
            </div>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-header">{{ __('Usage vs limits') }}</div><ul class="list-group list-group-flush">
            <li class="list-group-item d-flex">{{ __('Users') }}<span class="ms-auto">{{ $usage['users'] }} / {{ $c->plan?->max_users ?? '∞' }}</span></li>
            <li class="list-group-item d-flex">{{ __('Branches') }}<span class="ms-auto">{{ $usage['branches'] }} / {{ $c->plan?->max_branches ?? '∞' }}</span></li>
            <li class="list-group-item d-flex">{{ __('Storage') }}<span class="ms-auto">{{ $usage['storage_mb'] }} MB / {{ $c->plan?->max_storage_mb ? $c->plan->max_storage_mb.' MB' : '∞' }}</span></li>
            <li class="list-group-item d-flex">{{ __('Invoices / job cards') }}<span class="ms-auto">{{ $usage['invoices'] }} / {{ $usage['job_cards'] }}</span></li>
        </ul></div>
        <div class="card"><div class="card-header">{{ __('Subscription invoices') }}</div><ul class="list-group list-group-flush small">
            @forelse($invoices as $i)<li class="list-group-item d-flex">{{ $i->number }} · {{ $i->period_start->format('Y-m-d') }} → {{ $i->period_end->format('Y-m-d') }}<span class="ms-auto">{{ money($i->amount, false) }} {!! status_badge($i->status === 'unpaid' ? 'pending' : 'paid') !!}</span></li>
            @empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul></div>
    </div>
</div>
@endsection
