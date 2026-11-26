@extends('layouts.admin')
@section('title', __('Domains & e-mail'))
@section('content')
@php
    $stColor = ['active' => 'success', 'pending' => 'warning', 'disabled' => 'secondary'];
    $rqColor = ['new' => 'primary', 'processing' => 'warning', 'completed' => 'success', 'rejected' => 'secondary', 'cancelled' => 'secondary'];
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-globe2 text-brand"></i> {{ __('Domains & e-mail') }}</h4>
        <div class="small text-body-secondary">{{ __('Client custom domains, domain requests and the platform SMTP server.') }}</div>
    </div>
</div>

{{-- client requests --}}
<div class="card mb-3">
    <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-inbox"></i> {{ __('Domain requests') }}
        <div class="ms-auto btn-group btn-group-sm">
            <a class="btn {{ request('status') ? 'btn-outline-secondary' : 'btn-secondary' }}" href="{{ route('admin.domains.index') }}">{{ __('Open') }}</a>
            @foreach(['completed', 'rejected', 'cancelled'] as $s)<a class="btn {{ request('status') === $s ? 'btn-secondary' : 'btn-outline-secondary' }}" href="{{ route('admin.domains.index', ['status' => $s]) }}">{{ status_label($s) }}</a>@endforeach
        </div>
    </div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light small"><tr><th>{{ __('Client') }}</th><th>{{ __('Request') }}</th><th>{{ __('Domain') }}</th><th>{{ __('Status') }}</th><th style="min-width:340px">{{ __('Handle') }}</th></tr></thead>
        <tbody>
        @forelse($requests as $r)
            <tr>
                <td><a href="{{ route('admin.clients.show', $r->company_id) }}" class="fw-semibold">{{ $r->company?->name }}</a>
                    <div class="small text-body-secondary">{{ $r->company?->plan?->name }} · {{ $r->created_at->format('Y-m-d') }} · {{ $r->requester?->name }}</div></td>
                <td class="small"><span class="badge text-bg-light border">{{ $r->type === 'register' ? __('Register a new domain') : __('Connect my domain') }}</span>
                    @if($r->type === 'register')<div>{{ $r->years }} {{ __('year(s)') }}@if($r->price) · {{ money($r->price) }}@endif</div>@endif
                    @if($r->notes)<div class="text-body-secondary">{{ \Illuminate\Support\Str::limit($r->notes, 80) }}</div>@endif</td>
                <td><span class="font-monospace fw-semibold" dir="ltr">{{ $r->domain }}</span>@if($r->alternatives)<div class="small text-body-secondary" dir="ltr">{{ $r->alternatives }}</div>@endif</td>
                <td><span class="badge text-bg-{{ $rqColor[$r->status] ?? 'secondary' }}">{{ status_label($r->status) }}</span>@if($r->handler)<div class="small text-body-secondary">{{ $r->handler->name }}</div>@endif</td>
                <td>
                    @if($r->isOpen())
                        <form method="post" action="{{ route('admin.domains.requests.handle', $r) }}" class="d-flex flex-wrap gap-1">@csrf
                            <select name="status" class="form-select form-select-sm" style="max-width:150px" aria-label="{{ __('Status') }}">
                                <option value="processing">{{ status_label('processing') }}</option>
                                <option value="completed">{{ __('Completed — assign the domain') }}</option>
                                <option value="rejected">{{ status_label('rejected') }}</option>
                            </select>
                            <input name="price" type="number" step="0.001" class="form-control form-control-sm" style="max-width:100px" value="{{ $r->price !== null ? (float) $r->price : '' }}" placeholder="{{ __('Price') }}" aria-label="{{ __('Price') }}">
                            <input name="admin_notes" class="form-control form-control-sm" style="max-width:180px" value="{{ $r->admin_notes }}" placeholder="{{ __('Notes') }}" aria-label="{{ __('Notes') }}">
                            <button class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                        </form>
                    @else
                        <span class="small text-body-secondary">{{ $r->admin_notes ?: '—' }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No domain requests.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

{{-- client domains --}}
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-globe"></i> {{ __('Client domains') }}</div>
    <div class="card-body border-bottom">
        <form method="post" action="{{ route('admin.domains.assign') }}" class="row g-2 align-items-end">@csrf
            <div class="col-md-4"><label class="form-label small mb-0" for="d_company">{{ __('Client') }}</label>
                <select id="d_company" name="company_id" class="form-select" required>@foreach($clients as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
            <div class="col-md-5"><label class="form-label small mb-0" for="d_domain">{{ __('Domain') }}</label>
                <input id="d_domain" name="domain" class="form-control @error('domain') is-invalid @enderror" dir="ltr" placeholder="erp.mygarage.om" value="{{ old('domain') }}">
                @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-3 d-grid"><button class="btn btn-primary"><i class="bi bi-link-45deg"></i> {{ __('Assign domain') }}</button></div>
        </form>
        <div class="small text-body-secondary mt-2">
            <i class="bi bi-info-circle"></i> {{ __('After assigning: (1) the client points the domain to :h (CNAME) or to the server IP (A record); (2) add the domain in cPanel as an alias / addon domain of the same folder and issue SSL (AutoSSL); (3) press "Verify DNS".', ['h' => $platformHost]) }}
        </div>
    </div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light small"><tr><th>{{ __('Client') }}</th><th>{{ __('Domain') }}</th><th>{{ __('DNS records to add') }}</th><th>{{ __('Status') }}</th><th class="text-end"></th></tr></thead>
        <tbody>
        @forelse($companies as $c)
            @php $records = app(\App\Modules\Saas\Services\DomainService::class)->instructions($c); @endphp
            <tr>
                <td><a href="{{ route('admin.clients.show', $c->id) }}" class="fw-semibold">{{ $c->name }}</a><div class="small text-body-secondary">{{ $c->plan?->name }} @if($c->plan && ! $c->plan->allow_custom_domain)<span class="badge text-bg-warning">{{ __('Not in plan') }}</span>@endif</div></td>
                <td><a class="font-monospace fw-semibold" dir="ltr" href="https://{{ $c->custom_domain }}" target="_blank" rel="noopener">{{ $c->custom_domain }}</a>
                    @if($c->domain_verified_at)<div class="small text-body-secondary">{{ __('Verified') }} {{ $c->domain_verified_at->format('Y-m-d') }}</div>@endif</td>
                <td class="small font-monospace" dir="ltr">@foreach($records as $rec)<div><span class="badge text-bg-light border">{{ $rec['type'] }}</span> {{ $rec['name'] }} → {{ $rec['value'] }}</div>@endforeach</td>
                <td><span class="badge text-bg-{{ $stColor[$c->domain_status] ?? 'secondary' }}">{{ status_label($c->domain_status ?: 'pending') }}</span></td>
                <td class="text-end text-nowrap">
                    <form class="d-inline" method="post" action="{{ route('admin.domains.verify', $c) }}">@csrf<button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i> {{ __('Verify DNS') }}</button></form>
                    <form class="d-inline" method="post" action="{{ route('admin.domains.status', $c) }}">@csrf
                        @if($c->domain_status === 'active')<input type="hidden" name="status" value="disabled"><button class="btn btn-sm btn-outline-secondary">{{ __('Disable') }}</button>
                        @else<input type="hidden" name="status" value="active"><button class="btn btn-sm btn-outline-success" title="{{ __('Activate without the DNS check') }}">{{ __('Activate') }}</button>@endif
                    </form>
                    <form class="d-inline" method="post" action="{{ route('admin.domains.assign') }}" data-confirm="{{ __('Remove the custom domain of :c?', ['c' => $c->name]) }}">@csrf<input type="hidden" name="company_id" value="{{ $c->id }}"><input type="hidden" name="domain" value=""><button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Remove') }}" title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No client uses a custom domain yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

{{-- platform SMTP --}}
<div class="card mb-3" id="smtp">
    <div class="card-header"><i class="bi bi-envelope-at"></i> {{ __('Platform e-mail (SMTP)') }}
        @if($smtp)<span class="badge text-bg-success ms-2">{{ __('Active') }}</span>@else<span class="badge text-bg-secondary ms-2">{{ __('Using .env settings') }}</span>@endif</div>
    <div class="card-body">
        <p class="small text-body-secondary">{{ __('Used for system e-mails (trial requests, password reset, notifications) and for clients that have not set up their own SMTP.') }}</p>
        @include('partials.smtp-form', ['action' => route('admin.domains.smtp'), 'testAction' => route('admin.domains.smtp.test'), 'c' => $smtp, 'withActive' => false])
        @if($smtp)
            <form method="post" action="{{ route('admin.domains.smtp') }}" class="mt-2" data-confirm="{{ __('Stop using the platform SMTP?') }}">@csrf<input type="hidden" name="disable" value="1"><button class="btn btn-sm btn-outline-danger">{{ __('Disable platform SMTP') }}</button></form>
        @endif
    </div>
</div>
@endsection
