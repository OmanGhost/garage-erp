@extends('layouts.admin')
@section('title', __('New tenant'))
@section('content')
<h4 class="mb-3">{{ __('New tenant (company)') }}</h4>
<form method="post" action="{{ route('admin.companies.store') }}" class="card" style="max-width:900px">@csrf <x-idem/><div class="card-body row g-3">
    <div class="col-md-6"><label class="form-label required">{{ __('Company name (Arabic)') }}</label><input name="name" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">{{ __('Company name (English)') }}</label><input name="name_en" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">{{ __('Store slug') }}</label><input name="slug" class="form-control" placeholder="my-garage"></div>
    <div class="col-md-4"><label class="form-label">{{ __('C.R. number') }}</label><input name="cr_number" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">{{ __('VAT number') }}</label><input name="vat_number" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">{{ __('Phone') }}</label><input name="phone" class="form-control" dir="ltr"></div>
    <div class="col-md-6"><label class="form-label">{{ __('Email') }}</label><input type="email" name="email" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">{{ __('Plan') }}</label><select name="plan_id" class="form-select">@foreach($plans as $p)<option value="{{ $p->id }}">{{ $p->name }} — {{ money($p->price_monthly, false) }} OMR / {{ __('month') }} ({{ $p->trial_days }} {{ __('trial days') }})</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label">{{ __('Billing cycle') }}</label><select name="billing_cycle" class="form-select"><option value="monthly">{{ __('Monthly') }}</option><option value="yearly">{{ __('Yearly') }}</option></select></div>
    <div class="col-12"><div class="form-section-title">{{ __('Company administrator') }}</div></div>
    <div class="col-md-4"><label class="form-label required">{{ __('Name') }}</label><input name="admin_name" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label required">{{ __('Email') }}</label><input type="email" name="admin_email" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label required">{{ __('Password') }}</label><input type="password" name="admin_password" class="form-control" minlength="10" required autocomplete="new-password"></div>
</div><div class="card-footer text-end"><button class="btn btn-primary">{{ __('Create & provision') }}</button></div></form>
@endsection
