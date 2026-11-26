{{-- Subscription page: the company's custom domain and domain requests --}}
@php
    $plan = $company->plan;
    $open = $domainRequests->first(fn ($r) => $r->isOpen());
    $stColor = ['active' => 'success', 'pending' => 'warning', 'disabled' => 'secondary'];
@endphp
<div class="card mt-3" id="domain">
    <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-globe2"></i> {{ __('Custom domain') }}
        @if($plan?->allow_custom_domain)<span class="badge text-bg-success ms-auto">{{ __('Included in your plan') }}</span>
        @else<span class="badge text-bg-light border ms-auto">{{ __('Not included in your plan') }}</span>@endif
    </div>
    <div class="card-body">
        @if($company->custom_domain)
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="fs-5 font-monospace fw-semibold" dir="ltr">{{ $company->custom_domain }}</span>
                <span class="badge text-bg-{{ $stColor[$company->domain_status] ?? 'secondary' }}">{{ status_label($company->domain_status ?: 'pending') }}</span>
                @if($company->domain_status === 'active')<a class="small" href="https://{{ $company->custom_domain }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> {{ __('Open') }}</a>@endif
            </div>
            @if($company->domain_status !== 'active')
                <div class="small mb-1">{{ __('Add these DNS records at your domain provider:') }}</div>
                <div class="small font-monospace bg-body-tertiary rounded p-2" dir="ltr">
                    @foreach(app(\App\Modules\Saas\Services\DomainService::class)->instructions($company) as $rec)<div><b>{{ $rec['type'] }}</b> {{ $rec['name'] }} → {{ $rec['value'] }}</div>@endforeach
                </div>
                <div class="small text-body-secondary mt-1">{{ __('One of the first two records is enough. The domain is activated after the platform checks the DNS (it can take up to 24 hours).') }}</div>
            @endif
        @else
            <p class="small text-body-secondary mb-2">{{ __('Use your own address (for example erp.yourgarage.om) to open the system with your name and logo.') }}</p>
        @endif

        @if($open)
            <div class="alert alert-info small mb-0 mt-2 d-flex align-items-center gap-2">
                <i class="bi bi-hourglass-split"></i>
                <div class="flex-grow-1">{{ __('Request in progress') }}: <b dir="ltr">{{ $open->domain }}</b> — {{ $open->type === 'register' ? __('Register a new domain') : __('Connect my domain') }} · {{ status_label($open->status) }}
                    @if($open->price) · {{ money($open->price) }}@endif</div>
                @if($open->status === 'new')
                    <form method="post" action="{{ route('domain-requests.cancel', $open) }}" data-confirm="{{ __('Cancel this domain request?') }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ __('Cancel') }}</button></form>
                @endif
            </div>
        @elseif($plan?->allow_custom_domain)
            @can('settings.update')
            <form method="post" action="{{ route('domain-requests.store') }}" class="row g-2 mt-1" x-data="{ type: '{{ old('type', 'register') }}' }">@csrf
                <div class="col-12">
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <input type="radio" class="btn-check" name="type" id="dt_register" value="register" x-model="type"><label class="btn btn-outline-primary" for="dt_register"><i class="bi bi-bag-plus"></i> {{ __('Register a new domain') }}</label>
                        <input type="radio" class="btn-check" name="type" id="dt_connect" value="connect" x-model="type"><label class="btn btn-outline-primary" for="dt_connect"><i class="bi bi-link-45deg"></i> {{ __('Connect my domain') }}</label>
                    </div>
                </div>
                <div class="col-md-7"><label class="form-label small mb-0" for="dr_domain">{{ __('Domain') }}</label>
                    <input id="dr_domain" name="domain" class="form-control @error('domain') is-invalid @enderror" dir="ltr" placeholder="mygarage.om / erp.mygarage.com" value="{{ old('domain') }}" required>
                    @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-5" x-show="type === 'register'"><label class="form-label small mb-0" for="dr_years">{{ __('Years') }}</label>
                    <select id="dr_years" name="years" class="form-select">@foreach([1, 2, 3, 5] as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</select></div>
                <div class="col-12" x-show="type === 'register'"><input name="alternatives" class="form-control form-control-sm" dir="ltr" placeholder="{{ __('Alternative names if it is taken (optional)') }}" value="{{ old('alternatives') }}" aria-label="{{ __('Alternative names') }}"></div>
                <div class="col-12"><textarea name="notes" rows="2" class="form-control form-control-sm" placeholder="{{ __('Notes (optional)') }}" aria-label="{{ __('Notes') }}">{{ old('notes') }}</textarea></div>
                <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                    <button class="btn btn-primary"><i class="bi bi-send"></i> {{ __('Send request') }}</button>
                    <span class="small text-body-secondary" x-show="type === 'register'">@if($plan->domain_fee){{ __('Registration fee: :p per year.', ['p' => money($plan->domain_fee)]) }}@else{{ __('The platform will confirm the price before registering.') }}@endif</span>
                    <span class="small text-body-secondary" x-show="type === 'connect'" x-cloak>{{ __('You keep your domain; we give you the DNS records to add.') }}</span>
                </div>
            </form>
            @endcan
        @else
            <div class="small text-body-secondary mt-2"><i class="bi bi-stars"></i> {{ __('Available on plans with a custom domain. Contact the platform administrator to upgrade.') }}
                @php $withDomain = $plans->where('allow_custom_domain', true)->pluck('name')->implode('، '); @endphp
                @if($withDomain)<div>{{ __('Plans that include it') }}: {{ $withDomain }}</div>@endif</div>
        @endif

        @php $past = $domainRequests->reject(fn ($r) => $r->isOpen()); @endphp
        @if($past->isNotEmpty())
            <div class="small fw-semibold mt-3">{{ __('Previous requests') }}</div>
            <ul class="list-unstyled small mb-0">@foreach($past as $r)<li><span dir="ltr">{{ $r->domain }}</span> · {{ status_label($r->status) }} · {{ $r->created_at->format('Y-m-d') }}@if($r->admin_notes) — {{ $r->admin_notes }}@endif</li>@endforeach</ul>
        @endif
    </div>
</div>
