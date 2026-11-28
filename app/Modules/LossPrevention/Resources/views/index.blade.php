@extends('layouts.app')
@section('title', __('Fraud & loss prevention'))
@section('content')
@php
    $sevColor = ['high' => 'danger', 'medium' => 'warning', 'low' => 'secondary'];
    $ruleIcon = ['high_discount' => 'bi-percent', 'below_cost' => 'bi-graph-down-arrow', 'price_override' => 'bi-tag', 'price_change' => 'bi-tags',
        'payment_cancelled' => 'bi-receipt-cutoff', 'credit_note' => 'bi-arrow-return-left', 'void_sale' => 'bi-cart-x', 'stock_write_off' => 'bi-box-arrow-up',
        'frequent_adjustments' => 'bi-arrow-repeat', 'part_adjustments' => 'bi-boxes', 'unbilled_parts' => 'bi-wrench', 'cash_shortage' => 'bi-cash-coin',
        'cash_overage' => 'bi-cash-stack', 'after_hours' => 'bi-moon-stars'];
@endphp
<x-page-header :title="__('Fraud & loss prevention')" icon="bi-incognito" :breadcrumbs="[__('Administration'), __('Fraud & loss prevention')]">
    <form method="post" action="{{ route('loss-prevention.verify') }}">@csrf<button class="btn btn-outline-secondary"><i class="bi bi-shield-check"></i> {{ __('Verify audit log') }}</button></form>
    @can('settings.update')@can('fraud_alerts.update')<button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#lpSettings"><i class="bi bi-sliders"></i> {{ __('Thresholds') }}</button>@endcan @endcan
</x-page-header>

@if($integrity)
    <div class="alert {{ $integrity['tampered'] ? 'alert-danger' : 'alert-success' }}">
        <i class="bi {{ $integrity['tampered'] ? 'bi-shield-x' : 'bi-shield-check' }}"></i>
        {{ __('Audit records checked: :c · sealed and intact: :s · written before sealing: :u', ['c' => number_format($integrity['checked']), 's' => number_format($integrity['sealed']), 'u' => number_format($integrity['unsealed'])]) }}
        @if($integrity['tampered'])<div class="mt-1 fw-semibold">{{ __('Changed records (ID)') }}: {{ implode(', ', array_slice($integrity['tampered'], 0, 50)) }}</div>@endif
    </div>
@endif

@can('settings.update')@can('fraud_alerts.update')
<div class="collapse mb-3" id="lpSettings"><form method="post" action="{{ route('loss-prevention.settings') }}" class="card">@csrf
    <div class="card-header fw-semibold"><i class="bi bi-sliders me-1"></i>{{ __('Thresholds') }}</div>
    <div class="card-body row g-2">
        @foreach([
            'max_discount_percent' => __('Maximum manual discount (%)'), 'price_override_percent' => __('Price below the price list (%)'),
            'price_drop_percent' => __('Selling price cut (%)'), 'large_amount' => __('Large amount (high severity)'),
            'write_off_min' => __('Stock write-off from (value)'), 'adjustments_per_week' => __('Adjustments per user per week'),
            'part_adjustments_30d' => __('Adjustments of one part in 30 days'), 'cash_difference_min' => __('Cash difference from'),
            'after_hours_grace_minutes' => __('Grace around working hours (minutes)'),
        ] as $k => $l)
            <div class="col-6 col-md-4 col-xl-3"><label class="form-label small">{{ $l }}</label><input type="number" step="0.001" min="0" name="{{ $k }}" class="form-control" value="{{ $settings[$k] }}" required></div>
        @endforeach
        <div class="col-12 form-text">{{ __('Working hours come from the company working hours and holidays.') }}</div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Save') }}</button></div>
</form></div>
@endcan @endcan

<div class="row g-3 mb-3">
    @foreach([
        [__('Waiting for review'), $stats['open'], 'bi-hourglass-split', 'warning'],
        [__('High severity open'), $stats['high'], 'bi-exclamation-octagon', 'danger'],
        [__('Alerts in 30 days'), $stats['month'], 'bi-bell', 'primary'],
        [__('Confirmed cases (30 days)'), $stats['substantiated'], 'bi-person-x', 'danger'],
        [__('Amount at risk (30 days)'), money($stats['amount']), 'bi-cash-coin', 'secondary'],
    ] as [$l, $v, $i, $c])
        <div class="col-6 col-lg"><div class="card h-100"><div class="card-body d-flex align-items-center gap-2">
            <i class="bi {{ $i }} fs-3 text-{{ $c }}"></i><div><div class="small text-body-secondary">{{ $l }}</div><div class="fs-5 fw-bold">{{ $v }}</div></div>
        </div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                <div class="btn-group btn-group-sm" role="group">
                    @foreach(['unreviewed' => status_label('unreviewed'), 'substantiated' => status_label('substantiated'), 'cleared' => status_label('cleared'), 'all' => __('All')] as $k => $l)
                        <a href="{{ request()->fullUrlWithQuery(['status' => $k, 'page' => null]) }}" class="btn btn-outline-secondary {{ $status === $k ? 'active' : '' }}">{{ $l }}</a>
                    @endforeach
                </div>
                <form method="get" class="ms-auto d-flex flex-wrap gap-1">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <select name="severity" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('Severity') }}"><option value="">{{ __('All severities') }}</option>@foreach(['high', 'medium', 'low'] as $s)<option value="{{ $s }}" @selected(request('severity') === $s)>{{ status_label($s) }}</option>@endforeach</select>
                    <select name="rule" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('Rule') }}"><option value="">{{ __('All rules') }}</option>@foreach(\App\Modules\LossPrevention\Models\FraudAlert::RULES as $r)<option value="{{ $r }}" @selected(request('rule') === $r)>{{ status_label($r) }}</option>@endforeach</select>
                    <select name="user_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('User') }}"><option value="">{{ __('All users') }}</option>@foreach($users as $id => $n)<option value="{{ $id }}" @selected((int) request('user_id') === $id)>{{ $n }}</option>@endforeach</select>
                </form>
            </div>
            <div class="list-group list-group-flush">
                @forelse($alerts as $a)
                    <a href="{{ route('loss-prevention.show', $a) }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-center">
                        <i class="bi {{ $ruleIcon[$a->rule] ?? 'bi-exclamation-triangle' }} fs-4 text-{{ $sevColor[$a->severity] ?? 'secondary' }}"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $a->title }} @if($a->occurrences > 1)<span class="badge text-bg-dark">×{{ $a->occurrences }}</span>@endif</div>
                            <div class="small text-body-secondary">{{ status_label($a->rule) }} · {{ $a->user?->name ?? __('System') }} · {{ $a->last_seen_at?->format('Y-m-d H:i') }}</div>
                        </div>
                        <div class="text-end">
                            <span class="badge text-bg-{{ $sevColor[$a->severity] ?? 'secondary' }}">{{ status_label($a->severity) }}</span>
                            @if($a->amount !== null)<div class="small font-monospace mt-1">{{ money($a->amount, false) }}</div>@endif
                            @if($a->status !== 'unreviewed')<div class="mt-1">{!! status_badge($a->status) !!}</div>@endif
                        </div>
                    </a>
                @empty
                    <div class="empty-state p-4"><i class="bi bi-shield-check"></i>{{ __('No alerts here.') }}</div>
                @endforelse
            </div>
            @if($alerts->hasPages())<div class="card-footer">{{ $alerts->links() }}</div>@endif
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card mb-3">
            <div class="card-header fw-semibold">{{ __('By rule (30 days)') }}</div>
            <ul class="list-group list-group-flush small">
                @forelse($byRule as $r => $n)
                    <li class="list-group-item d-flex"><a href="{{ route('loss-prevention.index', ['status' => 'all', 'rule' => $r]) }}">{{ status_label($r) }}</a><span class="ms-auto fw-semibold">{{ $n }}</span></li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('Nothing yet.') }}</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header fw-semibold">{{ __('Users with most alerts (30 days)') }}</div>
            <ul class="list-group list-group-flush small">
                @forelse($byUser as $u)
                    <li class="list-group-item d-flex"><a href="{{ route('loss-prevention.index', ['status' => 'all', 'user_id' => $u->user_id]) }}">{{ $u->user?->name }}</a>
                        <span class="ms-auto">@if($u->h)<span class="badge text-bg-danger">{{ $u->h }}</span> @endif<b>{{ $u->n }}</b></span></li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('Nothing yet.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
