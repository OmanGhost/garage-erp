@extends('layouts.admin')
@section('title', __('Trial requests'))
@section('content')
@php
    use App\Modules\Saas\Services\TrialInspector;
    $colors = ['new' => 'primary', 'contacted' => 'warning', 'provisioned' => 'success', 'rejected' => 'secondary'];
    $icons = ['new' => 'bi-inbox', 'contacted' => 'bi-telephone-outbound', 'provisioned' => 'bi-check2-circle', 'rejected' => 'bi-x-circle'];
    $types = __('site.form.types');
    $risk = ['low' => ['success', __('Low risk'), 'bi-shield-check'], 'medium' => ['warning', __('Review'), 'bi-shield-exclamation'],
             'high' => ['danger', __('High risk'), 'bi-shield-x'], 'unknown' => ['secondary', __('Not checked'), 'bi-shield']];
    $deviceIcon = ['mobile' => 'bi-phone', 'tablet' => 'bi-tablet', 'desktop' => 'bi-laptop', 'bot' => 'bi-robot'];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-rocket-takeoff text-brand"></i> {{ __('Trial requests') }}</h4>
        <div class="small text-body-secondary">{{ __('Requests sent from the website. Open a request to verify it, then create the trial company.') }}</div>
    </div>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('site.home') }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> {{ __('View website') }}</a>
</div>

{{-- status summary --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md">
        <a class="card kpi h-100 text-decoration-none {{ request('status') ? '' : 'border-dark' }}" href="{{ route('admin.trials.index', request()->only('country')) }}">
            <div class="card-body py-2"><div class="kpi-label"><i class="bi bi-collection"></i> {{ __('All') }}</div><div class="kpi-value fs-4">{{ $counts->sum() }}</div></div>
        </a>
    </div>
    @foreach(\App\Modules\Saas\Models\TrialRequest::STATUSES as $st)
        <div class="col-6 col-md">
            <a class="card kpi h-100 text-decoration-none {{ request('status') === $st ? 'border-'.$colors[$st].' border-2' : '' }}" href="{{ route('admin.trials.index', ['status' => $st] + request()->only('country')) }}">
                <div class="card-body py-2"><div class="kpi-label text-{{ $colors[$st] }}"><i class="bi {{ $icons[$st] }}"></i> {{ status_label($st) }}</div><div class="kpi-value fs-4">{{ $counts[$st] ?? 0 }}</div></div>
            </a>
        </div>
    @endforeach
</div>

{{-- filters --}}
<form class="card card-body py-2 mb-3" method="get">
    <div class="row g-2 align-items-center">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <div class="col-md-6"><div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" name="q" value="{{ request('q') }}" placeholder="{{ __('Company, name, email, phone or IP...') }}">
        </div></div>
        <div class="col-md-4">
            <select name="country" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All countries') }}</option>
                @foreach($countries as $code => $n)<option value="{{ $code }}" @selected(strtoupper((string) request('country')) === $code)>{{ TrialInspector::countryName($code) }} ({{ $n }})</option>@endforeach
            </select>
        </div>
        <div class="col-md-2 d-grid"><button class="btn btn-sm btn-primary">{{ __('Search') }}</button></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 trials-table">
            <thead class="table-light small">
                <tr>
                    <th>{{ __('Company / contact') }}</th>
                    <th>{{ __('Contact') }}</th>
                    <th>{{ __('Location') }}</th>
                    <th>{{ __('Request') }}</th>
                    <th>{{ __('Verification') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($trials as $t)
                @php
                    $a = $assessments[$t->id];
                    [$rc, $rl, $ri] = $risk[$a['risk']];
                    $dev = $t->meta['request']['device'] ?? [];
                    $warn = collect($a['checks'])->whereIn('level', ['bad', 'warn'])->count();
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('admin.trials.show', $t) }}" class="fw-semibold text-decoration-none">{{ $t->company_name }}</a>
                        <div class="small text-body-secondary">{{ $t->name }}</div>
                        <div class="small text-body-tertiary" title="{{ $t->created_at->format('Y-m-d H:i') }}"><i class="bi bi-clock"></i> {{ $t->created_at->diffForHumans() }}</div>
                    </td>
                    <td class="small">
                        <div><i class="bi bi-telephone text-body-tertiary"></i> <bdi dir="ltr">{{ $t->phone }}</bdi></div>
                        <div class="text-truncate" style="max-width:220px"><i class="bi bi-envelope text-body-tertiary"></i> <bdi dir="ltr">{{ $t->email }}</bdi></div>
                    </td>
                    <td class="small">
                        @if($t->country_code)
                            <span class="badge text-bg-light border font-monospace">{{ $t->country_code }}</span> {{ TrialInspector::countryName($t->country_code) }}
                        @else
                            <span class="text-body-tertiary">{{ __('Unknown') }}</span>
                        @endif
                        <div class="text-body-secondary">{{ $t->city ?: '—' }}</div>
                    </td>
                    <td class="small">
                        <div>{{ $types[$t->business_type] ?? $t->business_type }}@if($t->branches) · {{ $t->branches }} {{ __('Branches') }}@endif</div>
                        <div class="text-body-secondary">{{ $t->plan?->name ?? '—' }}
                            @if($dev)· <i class="bi {{ $deviceIcon[$dev['device'] ?? 'desktop'] ?? 'bi-laptop' }}" title="{{ trim(($dev['browser'] ?? '').' · '.($dev['os'] ?? ''), ' ·') }}"></i>@endif
                        </div>
                    </td>
                    <td>
                        <span class="badge text-bg-{{ $rc }}"><i class="bi {{ $ri }}"></i> {{ $rl }}</span>
                        @if($warn)<div class="small text-body-secondary mt-1">{{ trans_choice('{1} :n note|[2,*] :n notes', $warn, ['n' => $warn]) }}</div>@endif
                    </td>
                    <td>
                        <span class="badge rounded-pill text-bg-{{ $colors[$t->status] ?? 'secondary' }}"><i class="bi {{ $icons[$t->status] ?? 'bi-dot' }}"></i> {{ status_label($t->status) }}</span>
                        @if($t->company)<div class="small mt-1"><a href="{{ route('admin.clients.show', $t->company->id) }}">{{ $t->company->name }}</a></div>@endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.trials.show', $t) }}"><i class="bi bi-eye"></i> {{ __('Open') }}</a>
                        <a class="btn btn-sm btn-outline-success" href="https://wa.me/{{ preg_replace('/\D/', '', $t->phone) }}" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-body-secondary py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>{{ __('No records found.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $trials->links() }}</div>
@endsection
