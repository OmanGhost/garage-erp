@extends('layouts.app')
@section('title', __('Company Settings'))
@section('content')
@php $s = $company->settings ?? []; $val = fn($k) => old('settings.'.str_replace('.', '__', $k), $s[$k] ?? $defaults[$k]); @endphp
<x-page-header :title="__('Company Settings')" icon="bi-gear" :breadcrumbs="[__('Administration'), __('Company Settings')]"/>
<form method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data">@csrf @method('put') <x-idem/>
<div class="row g-3">
    <div class="col-xl-6">
        <div class="card mb-3"><div class="card-header">{{ __('Company profile & identity') }}</div><div class="card-body row g-2">
            <div class="col-md-6"><label class="form-label required">{{ __('Name (Arabic)') }}</label><input name="name_ar" value="{{ old('name_ar', $company->name_ar ?? $company->name) }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">{{ __('Name (English)') }}</label><input name="name_en" value="{{ old('name_en', $company->name_en) }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">{{ __('Legal name') }}</label><input name="legal_name" value="{{ $company->legal_name }}" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">{{ __('C.R. number') }}</label><input name="cr_number" value="{{ $company->cr_number }}" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">{{ __('VAT number') }}</label><input name="vat_number" value="{{ $company->vat_number }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">{{ __('Phone') }}</label><input name="phone" value="{{ $company->phone }}" class="form-control" dir="ltr"></div>
            <div class="col-md-6"><label class="form-label">{{ __('Email') }}</label><input type="email" name="email" value="{{ $company->email }}" class="form-control"></div>
            <div class="col-md-8"><label class="form-label">{{ __('Address') }}</label><input name="address" value="{{ $company->address }}" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">{{ __('City') }}</label><input name="city" value="{{ $company->city }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">{{ __('Logo') }}</label>@if($company->logoUrl())<img src="{{ $company->logoUrl() }}" class="d-block mb-1" style="max-height:50px">@endif<input type="file" name="logo" accept="image/*" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">{{ __('Brand color') }}</label><input type="color" name="primary_color" value="{{ $company->primary_color }}" class="form-control form-control-color w-100"></div>
            <div class="col-md-3"><label class="form-label">{{ __('Default language') }}</label><select name="locale" class="form-select"><option value="ar" @selected($company->locale === 'ar')>العربية</option><option value="en" @selected($company->locale === 'en')>English</option></select></div>
        </div></div>
        <div class="card mb-3"><div class="card-header">{{ __('Currency & taxes') }}</div><div class="card-body row g-2">
            <div class="col-md-4"><label class="form-label">{{ __('Currency') }}</label><input value="{{ $company->currency_code }} ({{ $company->currency_decimals }} {{ __('decimals') }})" class="form-control" disabled></div>
            <div class="col-md-4"><label class="form-label">{{ __('Default VAT %') }}</label><input type="number" step="0.001" name="vat_rate" value="{{ (float) $company->vat_rate }}" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">{{ __('Time zone') }}</label><input value="{{ $company->timezone }}" class="form-control" disabled></div>
        </div></div>
        <div class="card mb-3"><div class="card-header">{{ __('Invoices & documents') }}</div><div class="card-body row g-2">
            <div class="col-md-6"><label class="form-label">{{ __('Invoice footer (Arabic)') }}</label><input name="settings[invoice__footer_ar]" value="{{ $val('invoice.footer_ar') }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">{{ __('Invoice footer (English)') }}</label><input name="settings[invoice__footer_en]" value="{{ $val('invoice.footer_en') }}" class="form-control"></div>
            <div class="col-12"><label class="form-label">{{ __('Terms & conditions') }}</label><textarea name="settings[invoice__terms]" class="form-control" rows="3">{{ $val('invoice.terms') }}</textarea></div>
            <div class="col-md-6"><label class="form-label">{{ __('Quotation validity (days)') }}</label><input type="number" name="settings[quotation__validity_days]" value="{{ $val('quotation.validity_days') }}" class="form-control"></div>
            <div class="col-md-6"><a href="{{ route('crud.index', 'document-sequences') }}" class="btn btn-outline-secondary mt-4 w-100">{{ __('Document numbering') }}</a></div>
        </div></div>
    </div>
    <div class="col-xl-6">
        <div class="card mb-3"><div class="card-header">{{ __('Inventory') }}</div><div class="card-body row g-2">
            <div class="col-md-6"><label class="form-label">{{ __('Valuation method') }}</label><select name="settings[inventory__valuation_method]" class="form-select"><option value="weighted_average" @selected($val('inventory.valuation_method') === 'weighted_average')>{{ __('Weighted average') }}</option><option value="fifo" @selected($val('inventory.valuation_method') === 'fifo')>FIFO</option></select></div>
            <div class="col-md-6 pt-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="settings[inventory__allow_negative]" value="1" @checked($val('inventory.allow_negative'))><label class="form-check-label">{{ __('Allow negative stock') }}</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="settings[inventory__low_stock_alerts]" value="1" @checked($val('inventory.low_stock_alerts'))><label class="form-check-label">{{ __('Low stock alerts') }}</label></div></div>
        </div></div>
        <div class="card mb-3"><div class="card-header">{{ __('Workshop workflow') }}</div><div class="card-body row g-2">
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="settings[workshop__require_quotation_approval]" value="1" @checked($val('workshop.require_quotation_approval'))><label class="form-check-label">{{ __('Require approved quotation before work starts') }}</label></div></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="settings[workshop__require_qc]" value="1" @checked($val('workshop.require_qc'))><label class="form-check-label">{{ __('Require quality inspection before delivery') }}</label></div></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="settings[workshop__require_payment_before_delivery]" value="1" @checked($val('workshop.require_payment_before_delivery'))><label class="form-check-label">{{ __('Require full payment before delivery (cash customers)') }}</label></div></div>
            <div class="col-md-6"><label class="form-label">{{ __('Default labour cost / hour') }}</label><input type="number" step="0.001" name="settings[workshop__default_labor_cost_per_hour]" value="{{ $val('workshop.default_labor_cost_per_hour') }}" class="form-control"></div>
        </div></div>
        <div class="card mb-3"><div class="card-header">{{ __('Sales & purchasing') }}</div><div class="card-body row g-2">
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="settings[sales__allow_credit_without_limit]" value="1" @checked($val('sales.allow_credit_without_limit'))><label class="form-check-label">{{ __('Allow credit sales above customer credit limit') }}</label></div></div>
            <div class="col-md-6"><label class="form-label">{{ __('Purchase approval limit') }}</label><input type="number" step="0.001" name="settings[purchasing__approval_limit]" value="{{ $val('purchasing.approval_limit') }}" class="form-control"></div>
        </div></div>
        <div class="card mb-3"><div class="card-header">{{ __('HR') }}</div><div class="card-body row g-2">
            <div class="col-md-6"><label class="form-label">{{ __('Overtime rate (×)') }}</label><input type="number" step="0.01" name="settings[hr__overtime_rate]" value="{{ $val('hr.overtime_rate') }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">{{ __('Working minutes per day') }}</label><input type="number" name="settings[hr__working_minutes_per_day]" value="{{ $val('hr.working_minutes_per_day') }}" class="form-control"></div>
        </div></div>
    </div>
</div>
@can('settings.update')<div class="text-end"><button class="btn btn-primary btn-lg">{{ __('Save settings') }}</button></div>@endcan
</form>

{{-- e-mail server used for this company's messages to customers (separate form) --}}
<div class="card mt-4" id="smtp">
    <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-envelope-at"></i> {{ __('Email (SMTP)') }}
        @if(! empty($smtp['host']) && $smtpActive)<span class="badge text-bg-success ms-auto">{{ __('Active') }}</span>
        @elseif($platformSmtp)<span class="badge text-bg-light border ms-auto">{{ __('Using the platform e-mail server') }}</span>
        @else<span class="badge text-bg-warning ms-auto">{{ __('Not configured') }}</span>@endif
    </div>
    <div class="card-body">
        <p class="small text-body-secondary">{{ __('Invoices, reminders and notifications to your customers are sent from your own e-mail address through this server.') }}</p>
        @can('settings.update')
            @include('partials.smtp-form', ['action' => route('settings.smtp'), 'testAction' => route('settings.smtp.test'), 'c' => $smtp, 'active' => $smtpActive])
        @else
            <div class="small">{{ $smtp['host'] ?? '—' }}</div>
        @endcan
    </div>
</div>
@endsection
