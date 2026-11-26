@extends('layouts.admin')
@section('title', __('Trial requests'))
@section('content')
@php
    $colors = ['new' => 'primary', 'contacted' => 'warning', 'provisioned' => 'success', 'rejected' => 'secondary'];
    $types = __('site.form.types');
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0">{{ __('Trial requests') }}</h4>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('site.home') }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> {{ __('View website') }}</a>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-sm {{ request('status') ? 'btn-outline-dark' : 'btn-dark' }}" href="{{ route('admin.trials.index') }}">{{ __('All') }} <span class="badge text-bg-light">{{ $counts->sum() }}</span></a>
    @foreach(\App\Modules\Saas\Models\TrialRequest::STATUSES as $st)
        <a class="btn btn-sm {{ request('status') === $st ? 'btn-'.$colors[$st] : 'btn-outline-'.$colors[$st] }}" href="{{ route('admin.trials.index', ['status' => $st]) }}">
            {{ status_label($st) }} <span class="badge text-bg-light">{{ $counts[$st] ?? 0 }}</span>
        </a>
    @endforeach
    <form class="ms-auto d-flex gap-2" method="get">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="{{ __('Search...') }}">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
</div>

@forelse($trials as $t)
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge text-bg-{{ $colors[$t->status] ?? 'secondary' }}">{{ status_label($t->status) }}</span>
                        <strong class="fs-5">{{ $t->company_name }}</strong>
                    </div>
                    <div>{{ $t->name }} · {{ $types[$t->business_type] ?? $t->business_type }} @if($t->branches)· {{ $t->branches }} {{ __('Branches') }}@endif</div>
                    <div class="small mt-1">
                        <a href="tel:{{ $t->phone }}" dir="ltr">{{ $t->phone }}</a> ·
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $t->phone) }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i></a> ·
                        <a href="mailto:{{ $t->email }}">{{ $t->email }}</a>
                    </div>
                    <div class="small text-body-secondary mt-1">
                        {{ $t->created_at->format('Y-m-d H:i') }} · {{ $t->city ?: '—' }} · {{ strtoupper($t->locale) }}
                        @if($t->plan) · {{ __('Plan') }}: {{ $t->plan->name }}@endif
                        @if($t->source) · <span title="{{ $t->source }}">{{ \Illuminate\Support\Str::limit($t->source, 40) }}</span>@endif
                    </div>
                    @if($t->message)<div class="border rounded p-2 mt-2 small bg-body-tertiary">{{ $t->message }}</div>@endif
                </div>

                <div class="col-lg-4">
                    @if($t->status === 'provisioned')
                        <div class="alert alert-success small mb-0">
                            <i class="bi bi-check-circle"></i> {{ __('Provisioned') }}
                            @if($t->company) — <a href="{{ route('admin.clients.show', $t->company->id) }}">{{ $t->company->name }}</a>@endif
                            <div class="text-body-secondary">{{ $t->handler?->name }} · {{ $t->handled_at?->format('Y-m-d H:i') }}</div>
                        </div>
                    @else
                        <form method="post" action="{{ route('admin.trials.update', $t) }}" class="d-grid gap-2">
                            @csrf @method('PUT')
                            <select name="status" class="form-select form-select-sm">
                                @foreach(['new', 'contacted', 'rejected'] as $st)<option value="{{ $st }}" @selected($t->status === $st)>{{ status_label($st) }}</option>@endforeach
                            </select>
                            <textarea name="admin_notes" rows="2" class="form-control form-control-sm" placeholder="{{ __('Internal notes') }}">{{ $t->admin_notes }}</textarea>
                            <button class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button>
                        </form>
                    @endif
                </div>

                <div class="col-lg-3">
                    @if($t->status !== 'provisioned')
                        <form method="post" action="{{ route('admin.trials.provision', $t) }}" class="d-grid gap-2" data-confirm="{{ __('Create a trial company and an admin account for this request?') }}" data-confirm-type="primary">
                            @csrf
                            <select name="plan_id" class="form-select form-select-sm" required>
                                @foreach($plans as $p)<option value="{{ $p->id }}" @selected(($t->plan_id ?? $plans->firstWhere('code', 'professional')?->id) === $p->id)>{{ $p->name }} ({{ $p->trial_days }} {{ __('days') }})</option>@endforeach
                            </select>
                            <button class="btn btn-sm btn-success"><i class="bi bi-rocket-takeoff"></i> {{ __('Create trial company') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="text-center text-body-secondary py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>{{ __('No records found.') }}</div>
@endforelse

{{ $trials->links() }}
@endsection
