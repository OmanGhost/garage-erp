@extends('layouts.admin')
@section('title', $t->company_name)
@section('content')
@php
    use App\Modules\Saas\Services\TrialInspector;
    $colors = ['new' => 'primary', 'contacted' => 'warning', 'provisioned' => 'success', 'rejected' => 'secondary'];
    $types = __('site.form.types');
    $m = $t->meta ?? [];
    $client = $m['client'] ?? [];
    $req = $m['request'] ?? [];
    $ip = $m['ip'] ?? [];
    $dev = $req['device'] ?? [];
    $risk = ['low' => ['success', __('Low risk'), 'bi-shield-check', __('No problems found in the automatic checks.')],
             'medium' => ['warning', __('Review'), 'bi-shield-exclamation', __('Some details need a look before creating the company.')],
             'high' => ['danger', __('High risk'), 'bi-shield-x', __('Strong signs that this request is not genuine.')],
             'unknown' => ['secondary', __('Not checked'), 'bi-shield', __('The network checks have not run yet. Use “Check again”.')]];
    [$rc, $rl, $ri, $rt] = $risk[$a['risk']];
    $lv = ['ok' => ['success', 'bi-check-circle-fill'], 'info' => ['info', 'bi-info-circle-fill'], 'warn' => ['warning', 'bi-exclamation-triangle-fill'], 'bad' => ['danger', 'bi-x-octagon-fill']];
    $yes = fn ($v) => $v === null ? '—' : ($v ? __('Yes') : __('No'));
    $phoneCountry = TrialInspector::phoneCountry($t->phone);
    $digits = preg_replace('/\D/', '', $t->phone);
@endphp

<nav aria-label="breadcrumb"><ol class="breadcrumb small mb-2">
    <li class="breadcrumb-item"><a href="{{ route('admin.trials.index') }}">{{ __('Trial requests') }}</a></li>
    <li class="breadcrumb-item active">#{{ $t->id }}</li>
</ol></nav>

{{-- header --}}
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h4 class="mb-0">{{ $t->company_name }}</h4>
    <span class="badge rounded-pill text-bg-{{ $colors[$t->status] ?? 'secondary' }}">{{ status_label($t->status) }}</span>
    <span class="badge text-bg-{{ $rc }}"><i class="bi {{ $ri }}"></i> {{ $rl }}</span>
    <span class="text-body-secondary small"><i class="bi bi-clock"></i> {{ $t->created_at->format('Y-m-d H:i') }} ({{ $t->created_at->diffForHumans() }})</span>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="tel:{{ $t->phone }}"><i class="bi bi-telephone"></i> {{ __('Call') }}</a>
        <a class="btn btn-sm btn-outline-success" href="https://wa.me/{{ $digits }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
        <a class="btn btn-sm btn-outline-primary" href="mailto:{{ $t->email }}"><i class="bi bi-envelope"></i> {{ __('Email') }}</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">

        {{-- verification summary --}}
        <div class="card mb-3 border-{{ $rc }} border-top border-3">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi {{ $ri }} text-{{ $rc }} fs-5"></i>
                <div><strong>{{ __('Request verification') }}</strong><div class="small text-body-secondary">{{ $rt }}</div></div>
                <form class="ms-auto" method="post" action="{{ route('admin.trials.check', $t) }}">@csrf
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> {{ __('Check again') }}</button>
                </form>
            </div>
            <ul class="list-group list-group-flush">
                @foreach($a['checks'] as $c)
                    @php [$cc, $ci] = $lv[$c['level']]; @endphp
                    <li class="list-group-item d-flex gap-3 align-items-start">
                        <i class="bi {{ $ci }} text-{{ $cc }} mt-1"></i>
                        <div class="flex-grow-1"><div class="fw-semibold">{{ $c['label'] }}</div><div class="small text-body-secondary">{{ $c['detail'] }}</div></div>
                    </li>
                @endforeach
            </ul>
            <div class="card-footer small text-body-secondary">
                {{ __('Last check') }}: {{ $t->checked_at?->format('Y-m-d H:i') ?? __('Not checked') }}
                @if(isset($ip['error'])) · <span class="text-danger">{{ __('IP lookup failed') }}: {{ $ip['error'] }}</span>@endif
            </div>
        </div>

        <div class="row g-3 mb-3">
            {{-- contact --}}
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-person-vcard"></i> {{ __('Contact details') }}</div>
                    <div class="card-body">
                        <dl class="row small mb-0 trial-dl">
                            <dt class="col-5">{{ __('Name') }}</dt><dd class="col-7">{{ $t->name }}</dd>
                            <dt class="col-5">{{ __('Company') }}</dt><dd class="col-7">{{ $t->company_name }}</dd>
                            <dt class="col-5">{{ __('Phone') }}</dt>
                            <dd class="col-7"><bdi dir="ltr">{{ $t->phone }}</bdi>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-copy="{{ $t->phone }}" title="{{ __('Copy') }}"><i class="bi bi-copy"></i></button>
                                @if($phoneCountry)<div class="text-body-secondary">{{ TrialInspector::countryName($phoneCountry) }}</div>@endif
                            </dd>
                            <dt class="col-5">{{ __('Email') }}</dt>
                            <dd class="col-7 text-break"><bdi dir="ltr">{{ $t->email }}</bdi>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-copy="{{ $t->email }}" title="{{ __('Copy') }}"><i class="bi bi-copy"></i></button>
                            </dd>
                            <dt class="col-5">{{ __('City') }}</dt><dd class="col-7">{{ $t->city ?: '—' }}</dd>
                            <dt class="col-5">{{ __('Language') }}</dt><dd class="col-7">{{ $t->locale === 'en' ? 'English' : 'العربية' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            {{-- request --}}
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-clipboard-check"></i> {{ __('Request details') }}</div>
                    <div class="card-body">
                        <dl class="row small mb-0 trial-dl">
                            <dt class="col-5">{{ __('Business type') }}</dt><dd class="col-7">{{ $types[$t->business_type] ?? ($t->business_type ?: '—') }}</dd>
                            <dt class="col-5">{{ __('Branches') }}</dt><dd class="col-7">{{ $t->branches ?: '—' }}</dd>
                            <dt class="col-5">{{ __('Requested plan') }}</dt><dd class="col-7">{{ $t->plan?->name ?? '—' }}</dd>
                            <dt class="col-5">{{ __('Source') }}</dt><dd class="col-7 text-break">{{ $t->source ?: __('Direct visit') }}</dd>
                        </dl>
                        @if($t->message)
                            <div class="small fw-semibold mt-3 mb-1">{{ __('Message') }}</div>
                            <div class="border rounded p-2 small bg-body-tertiary" style="white-space:pre-line">{{ $t->message }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- technical information --}}
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-cpu"></i> {{ __('Technical information') }}</div>
            <div class="card-body">
                <div class="row g-4 small">
                    <div class="col-md-6">
                        <h6 class="text-body-secondary text-uppercase small fw-bold mb-2"><i class="bi bi-globe2"></i> {{ __('Connection') }}</h6>
                        <dl class="row mb-0 trial-dl">
                            <dt class="col-5">{{ __('IP address') }}</dt>
                            <dd class="col-7"><bdi dir="ltr" class="font-monospace">{{ $t->ip_address ?: '—' }}</bdi>
                                @if($t->ip_address)<button type="button" class="btn btn-link btn-sm p-0 ms-1" data-copy="{{ $t->ip_address }}" title="{{ __('Copy') }}"><i class="bi bi-copy"></i></button>@endif
                            </dd>
                            <dt class="col-5">{{ __('Country') }}</dt>
                            <dd class="col-7">@if($t->country_code)<span class="badge text-bg-light border font-monospace">{{ $t->country_code }}</span> {{ TrialInspector::countryName($t->country_code) }}@else — @endif</dd>
                            <dt class="col-5">{{ __('Region / city') }}</dt><dd class="col-7">{{ trim(($ip['region'] ?? '').' / '.($ip['city'] ?? ''), ' /') ?: '—' }}</dd>
                            <dt class="col-5">{{ __('Internet provider') }}</dt><dd class="col-7">{{ $ip['isp'] ?? '—' }}@if(($ip['org'] ?? null) && ($ip['org'] ?? '') !== ($ip['isp'] ?? ''))<div class="text-body-secondary">{{ $ip['org'] }}</div>@endif</dd>
                            <dt class="col-5">ASN</dt><dd class="col-7"><bdi dir="ltr">{{ $ip['as'] ?? '—' }}</bdi></dd>
                            <dt class="col-5">{{ __('Mobile network') }}</dt><dd class="col-7">{{ $yes($ip['mobile'] ?? null) }}</dd>
                            <dt class="col-5">{{ __('VPN / proxy') }}</dt><dd class="col-7">{!! ($ip['proxy'] ?? null) ? '<span class="text-danger fw-semibold">'.e(__('Yes')).'</span>' : e($yes($ip['proxy'] ?? null)) !!}</dd>
                            <dt class="col-5">{{ __('Hosting / data centre') }}</dt><dd class="col-7">{!! ($ip['hosting'] ?? null) ? '<span class="text-warning fw-semibold">'.e(__('Yes')).'</span>' : e($yes($ip['hosting'] ?? null)) !!}</dd>
                            <dt class="col-5">{{ __('Connection time zone') }}</dt><dd class="col-7"><bdi dir="ltr">{{ $ip['timezone'] ?? '—' }}</bdi></dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-body-secondary text-uppercase small fw-bold mb-2"><i class="bi bi-display"></i> {{ __('Device and browser') }}</h6>
                        <dl class="row mb-0 trial-dl">
                            <dt class="col-5">{{ __('Device') }}</dt><dd class="col-7">{{ isset($dev['device']) ? __(ucfirst($dev['device'])) : '—' }}</dd>
                            <dt class="col-5">{{ __('Browser') }}</dt><dd class="col-7"><bdi dir="ltr">{{ $dev['browser'] ?? '—' }}</bdi></dd>
                            <dt class="col-5">{{ __('Operating system') }}</dt><dd class="col-7"><bdi dir="ltr">{{ $dev['os'] ?? ($client['platform'] ?? '—') }}</bdi></dd>
                            <dt class="col-5">{{ __('Screen') }}</dt><dd class="col-7"><bdi dir="ltr">{{ $client['screen'] ?? '—' }}</bdi>@isset($client['touch']) · {{ $client['touch'] ? __('Touch screen') : __('No touch') }}@endisset</dd>
                            <dt class="col-5">{{ __('Device time zone') }}</dt><dd class="col-7"><bdi dir="ltr">{{ $client['timezone'] ?? '—' }}</bdi></dd>
                            <dt class="col-5">{{ __('Browser languages') }}</dt><dd class="col-7"><bdi dir="ltr">{{ $client['languages'] ?? ($req['accept_language'] ?? '—') }}</bdi></dd>
                            <dt class="col-5">{{ __('Time to fill the form') }}</dt><dd class="col-7">{{ isset($req['fill_seconds']) ? ($req['fill_seconds'] < 60 ? __(':n seconds', ['n' => $req['fill_seconds']]) : __(':m min :s sec', ['m' => intdiv($req['fill_seconds'], 60), 's' => $req['fill_seconds'] % 60])) : '—' }}</dd>
                        </dl>
                    </div>
                    <div class="col-12">
                        <h6 class="text-body-secondary text-uppercase small fw-bold mb-2"><i class="bi bi-signpost-split"></i> {{ __('How they found us') }}</h6>
                        <dl class="row mb-0 trial-dl">
                            <dt class="col-md-3 col-5">{{ __('Referring site') }}</dt><dd class="col-md-9 col-7 text-break"><bdi dir="ltr">{{ $client['referrer'] ?? '—' }}</bdi></dd>
                            <dt class="col-md-3 col-5">{{ __('First page visited') }}</dt><dd class="col-md-9 col-7 text-break"><bdi dir="ltr">{{ $client['landing'] ?? '—' }}</bdi></dd>
                            <dt class="col-md-3 col-5">UTM</dt><dd class="col-md-9 col-7"><bdi dir="ltr">{{ collect(['source' => $req['utm_source'] ?? null, 'medium' => $req['utm_medium'] ?? null, 'campaign' => $req['utm_campaign'] ?? null])->filter()->map(fn ($v, $k) => "$k=$v")->implode(' · ') ?: '—' }}</bdi></dd>
                            <dt class="col-md-3 col-5">User agent</dt><dd class="col-md-9 col-7"><code class="small text-break d-block" dir="ltr">{{ $t->user_agent ?: '—' }}</code></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        {{-- related requests --}}
        @if($related->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-diagram-2"></i> {{ __('Other requests with the same email, phone or IP') }}</div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
                    <tbody>
                    @foreach($related as $r)
                        <tr>
                            <td><a href="{{ route('admin.trials.show', $r) }}">{{ $r->company_name }}</a><div class="text-body-secondary">{{ $r->name }}</div></td>
                            <td>
                                @if($r->email === $t->email)<span class="badge text-bg-light border">{{ __('email') }}</span>@endif
                                @if($r->phone === $t->phone)<span class="badge text-bg-light border">{{ __('phone') }}</span>@endif
                                @if($t->ip_address && $r->ip_address === $t->ip_address)<span class="badge text-bg-light border">IP</span>@endif
                            </td>
                            <td>{{ status_label($r->status) }}</td>
                            <td class="text-body-secondary text-nowrap">{{ $r->created_at->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        @endif
    </div>

    {{-- actions --}}
    <div class="col-xl-4">
        <div class="position-sticky" style="top: 1rem">
            @if($t->status === 'provisioned')
                <div class="card mb-3 border-success">
                    <div class="card-body">
                        <div class="fw-semibold text-success mb-1"><i class="bi bi-check-circle"></i> {{ __('Trial company created') }}</div>
                        @if($t->company)<a class="btn btn-sm btn-outline-success" href="{{ route('admin.clients.show', $t->company->id) }}"><i class="bi bi-building"></i> {{ $t->company->name }}</a>@endif
                        <div class="small text-body-secondary mt-2">{{ $t->handler?->name }} · {{ $t->handled_at?->format('Y-m-d H:i') }}</div>
                    </div>
                </div>
            @else
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-rocket-takeoff"></i> {{ __('Create trial company') }}</div>
                    <div class="card-body">
                        <form method="post" action="{{ route('admin.trials.provision', $t) }}" class="d-grid gap-2"
                              data-confirm="{{ __('Create a trial company and an admin account for this request?') }}" data-confirm-type="primary">
                            @csrf
                            <label class="form-label small mb-0" for="plan_id">{{ __('Plan') }}</label>
                            <select id="plan_id" name="plan_id" class="form-select" required>
                                @foreach($plans as $p)<option value="{{ $p->id }}" @selected(($t->plan_id ?? $plans->firstWhere('code', 'professional')?->id) === $p->id)>{{ $p->name }} — {{ $p->trial_days }} {{ __('days') }}</option>@endforeach
                            </select>
                            @if($a['risk'] === 'high')
                                <div class="alert alert-danger small py-2 mb-0"><i class="bi bi-shield-x"></i> {{ __('High risk: contact the person and confirm before creating the company.') }}</div>
                            @endif
                            <button class="btn btn-success"><i class="bi bi-rocket-takeoff"></i> {{ __('Create trial company') }}</button>
                            <div class="form-text">{{ __('An admin account is created with :e and a temporary password.', ['e' => $t->email]) }}</div>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-journal-text"></i> {{ __('Follow-up') }}</div>
                    <div class="card-body">
                        <form method="post" action="{{ route('admin.trials.update', $t) }}" class="d-grid gap-2">
                            @csrf @method('PUT')
                            <label class="form-label small mb-0" for="status">{{ __('Status') }}</label>
                            <select id="status" name="status" class="form-select">
                                @foreach(['new', 'contacted', 'rejected'] as $st)<option value="{{ $st }}" @selected($t->status === $st)>{{ status_label($st) }}</option>@endforeach
                            </select>
                            <label class="form-label small mb-0" for="admin_notes">{{ __('Internal notes') }}</label>
                            <textarea id="admin_notes" name="admin_notes" rows="4" class="form-control" placeholder="{{ __('e.g. called on Sunday, wants a demo next week') }}">{{ $t->admin_notes }}</textarea>
                            <button class="btn btn-primary"><i class="bi bi-check2"></i> {{ __('Save') }}</button>
                        </form>
                        @if($t->handled_at)<div class="small text-body-secondary mt-2">{{ __('Last update') }}: {{ $t->handler?->name }} · {{ $t->handled_at->format('Y-m-d H:i') }}</div>@endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    .trial-dl dt { font-weight: 500; color: var(--bs-secondary-color); }
    .trial-dl dd { margin-bottom: .45rem; }
</style>
<script>
    document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', () => {
        navigator.clipboard?.writeText(b.dataset.copy).then(() => window.ERP?.toast?.(@json(__('Copied')), 'success', { timeout: 1500 }));
    }));
</script>
@endsection
