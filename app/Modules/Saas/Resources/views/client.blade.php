@extends('layouts.admin')
@section('title', $c->displayName())
@section('content')
@php
    $ends = $c->subscription_status === 'trial' ? $c->trial_ends_at : $c->subscription_ends_at;
    $state = $c->trashed() ? 'archived' : (! $c->is_active ? 'suspended' : ($c->subscriptionActive() ? $c->subscription_status : 'expired'));
    $tab = request('tab', 'overview');
@endphp

<nav aria-label="breadcrumb"><ol class="breadcrumb small mb-2">
    <li class="breadcrumb-item"><a href="{{ route('admin.clients.index') }}">{{ __('Clients') }}</a></li>
    <li class="breadcrumb-item active">{{ $c->displayName() }}</li>
</ol></nav>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h4 class="mb-0">{{ $c->displayName() }}</h4>
    {!! status_badge($state) !!}
    @if($c->plan)<span class="badge text-bg-light border">{{ $c->plan->name }}</span>@endif
    <span class="text-body-secondary small">{{ $c->slug }} · {{ __('Created At') }} {{ $c->created_at?->format('Y-m-d') }}</span>
    <div class="ms-auto d-flex flex-wrap gap-2">
        @if($c->trashed())
            <form method="post" action="{{ route('admin.clients.restore', $c->id) }}">@csrf<button class="btn btn-success"><i class="bi bi-arrow-counterclockwise"></i> {{ __('Restore') }}</button></form>
        @else
            <form method="post" action="{{ route('admin.companies.enter', $c) }}">@csrf<button class="btn btn-outline-primary"><i class="bi bi-box-arrow-in-right"></i> {{ __('Enter tenant') }}</button></form>
            <form method="post" action="{{ route('admin.clients.toggle', $c) }}" data-confirm="{{ $c->is_active ? __('Suspend this client? Its users will not be able to sign in.') : __('Activate this client?') }}">@csrf
                <button class="btn {{ $c->is_active ? 'btn-outline-danger' : 'btn-success' }}"><i class="bi {{ $c->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i> {{ $c->is_active ? __('Suspend') : __('Activate') }}</button>
            </form>
        @endif
    </div>
</div>

@if($c->trashed())
    <div class="alert alert-secondary"><i class="bi bi-archive"></i> {{ __('This client is archived. Its data is kept but nobody can sign in until it is restored.') }}</div>
@endif

{{-- summary --}}
<div class="row g-3 mb-3">
    @foreach([
        [__('Subscription ends'), $ends?->format('Y-m-d') ?? '—', 'bi-calendar-event'],
        [__('Users'), $usage['users'].' / '.($c->plan?->max_users ?? '∞'), 'bi-people'],
        [__('Branches'), $usage['branches'].' / '.($c->plan?->max_branches ?? '∞'), 'bi-diagram-3'],
        [__('Customers'), number_format($usage['customers']), 'bi-person-vcard'],
        [__('Invoices / job cards'), number_format($usage['invoices']).' / '.number_format($usage['job_cards']), 'bi-receipt'],
        [__('Storage'), $usage['storage_mb'].' MB', 'bi-hdd'],
    ] as [$l, $v, $icon])
        <div class="col-6 col-md-4 col-xl-2"><div class="card kpi h-100"><div class="card-body">
            <div class="kpi-label"><i class="bi {{ $icon }}"></i> {{ $l }}</div><div class="kpi-value fs-5">{{ $v }}</div>
        </div></div></div>
    @endforeach
</div>

<ul class="nav nav-tabs mb-0">
    @foreach(['overview' => [__('Client details'), 'bi-building'], 'subscription' => [__('Subscription & modules'), 'bi-credit-card'],
              'users' => [__('Users'), 'bi-people'], 'invoices' => [__('Subscription invoices'), 'bi-receipt'], 'activity' => [__('Activity'), 'bi-clock-history']] as $k => [$l, $icon])
        <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => $k]) }}"><i class="bi {{ $icon }}"></i> {{ $l }}</a></li>
    @endforeach
</ul>
<div class="card border-top-0 rounded-top-0">
<div class="card-body">

@if($tab === 'overview')
    <div class="row g-4">
        <div class="col-lg-8">
            <form method="post" action="{{ route('admin.clients.profile', $c) }}" class="row g-3">@csrf @method('PUT')
                <div class="col-md-6"><label class="form-label required">{{ __('Company name (Arabic)') }}</label><input name="name" value="{{ old('name', $c->name_ar ?: $c->name) }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">{{ __('Company name (English)') }}</label><input name="name_en" value="{{ old('name_en', $c->name_en) }}" class="form-control" dir="ltr"></div>
                <div class="col-md-6"><label class="form-label">{{ __('Legal name') }}</label><input name="legal_name" value="{{ old('legal_name', $c->legal_name) }}" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">{{ __('C.R. number') }}</label><input name="cr_number" value="{{ old('cr_number', $c->cr_number) }}" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">{{ __('VAT number') }}</label><input name="vat_number" value="{{ old('vat_number', $c->vat_number) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">{{ __('Email') }}</label><input type="email" name="email" value="{{ old('email', $c->email) }}" class="form-control" dir="ltr"></div>
                <div class="col-md-6"><label class="form-label">{{ __('Phone') }}</label><input name="phone" value="{{ old('phone', $c->phone) }}" class="form-control" dir="ltr"></div>
                <div class="col-md-6"><label class="form-label">{{ __('Contact person') }}</label><input name="contact_name" value="{{ old('contact_name', $c->contact_name) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">{{ __('Contact phone') }}</label><input name="contact_phone" value="{{ old('contact_phone', $c->contact_phone) }}" class="form-control" dir="ltr"></div>
                <div class="col-md-4"><label class="form-label">{{ __('City') }}</label><input name="city" value="{{ old('city', $c->city) }}" class="form-control"></div>
                <div class="col-md-8"><label class="form-label">{{ __('Address') }}</label><input name="address" value="{{ old('address', $c->address) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">{{ __('Source') }}</label>
                    <select name="source" class="form-select"><option value="">—</option>
                        @foreach(['trial_request' => __('Website trial request'), 'direct' => __('Direct sale'), 'referral' => __('Referral'), 'other' => __('Other')] as $k => $l)
                            <option value="{{ $k }}" @selected(old('source', $c->source ?? ($trial ? 'trial_request' : null)) === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12"><label class="form-label">{{ __('Internal notes') }}</label><textarea name="admin_notes" rows="4" class="form-control" placeholder="{{ __('Visible to platform administrators only') }}">{{ old('admin_notes', $c->admin_notes) }}</textarea></div>
                @unless($c->trashed())<div class="col-12 text-end"><button class="btn btn-primary">{{ __('Save') }}</button></div>@endunless
            </form>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-body small">
                <div class="fw-semibold mb-2">{{ __('Quick contact') }}</div>
                @php $phone = $c->contact_phone ?: $c->phone; @endphp
                @if($phone)
                    <a class="btn btn-sm btn-outline-secondary mb-1" href="tel:{{ $phone }}"><i class="bi bi-telephone"></i> <span dir="ltr">{{ $phone }}</span></a>
                    <a class="btn btn-sm btn-outline-success mb-1" href="https://wa.me/{{ preg_replace('/\D/', '', $phone) }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                @endif
                @if($c->email)<a class="btn btn-sm btn-outline-secondary mb-1" href="mailto:{{ $c->email }}"><i class="bi bi-envelope"></i> {{ $c->email }}</a>@endif
                @if(! $phone && ! $c->email)<span class="text-body-secondary">—</span>@endif
            </div></div>
            @if($trial)
                <div class="card mb-3"><div class="card-body small">
                    <div class="fw-semibold mb-1"><i class="bi bi-globe"></i> {{ __('From website trial request') }}</div>
                    <div>{{ $trial->name }} · {{ $trial->created_at->format('Y-m-d') }}</div>
                    @if($trial->message)<div class="text-body-secondary mt-1">{{ $trial->message }}</div>@endif
                </div></div>
            @endif
            @unless($c->trashed())
                <div class="card border-danger-subtle"><div class="card-body small">
                    <div class="fw-semibold text-danger mb-1"><i class="bi bi-archive"></i> {{ __('Archive client') }}</div>
                    <p class="text-body-secondary mb-2">{{ __('Stops all access. Data is kept and the client can be restored later.') }}</p>
                    <form method="post" action="{{ route('admin.clients.archive', $c) }}" class="d-flex gap-2">@csrf @method('DELETE')
                        <input name="confirm_slug" class="form-control form-control-sm" placeholder="{{ __('Type :s to confirm', ['s' => $c->slug]) }}" dir="ltr" required>
                        <button class="btn btn-sm btn-danger text-nowrap">{{ __('Archive') }}</button>
                    </form>
                </div></div>
            @endunless
        </div>
    </div>

@elseif($tab === 'subscription')
    <div class="row g-4">
        <div class="col-lg-7">
            <form method="post" action="{{ route('admin.companies.update', $c) }}" class="row g-2">@csrf @method('put')
                <div class="col-md-4"><label class="form-label">{{ __('Plan') }}</label><select name="plan_id" class="form-select">@foreach($plans as $p)<option value="{{ $p->id }}" @selected($c->plan_id === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">{{ __('Status') }}</label><select name="subscription_status" class="form-select">@foreach(['trial', 'active', 'past_due', 'suspended', 'cancelled'] as $s)<option value="{{ $s }}" @selected($c->subscription_status === $s)>{{ status_label($s) }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">{{ __('Ends at') }}</label><input type="date" name="subscription_ends_at" value="{{ $c->subscription_ends_at?->toDateString() }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">{{ __('Trial ends') }}</label><input type="date" name="trial_ends_at" value="{{ $c->trial_ends_at?->toDateString() }}" class="form-control"></div>
                <div class="col-md-8"><label class="form-label">{{ __('Custom domain') }}</label><input name="custom_domain" value="{{ $c->custom_domain }}" class="form-control" dir="ltr" placeholder="erp.mygarage.om"></div>
                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" @checked($c->is_active)><label class="form-check-label" for="ia">{{ __('Tenant active') }}</label></div></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="override_modules" value="1" id="om" @checked(is_array($c->enabled_modules))><label class="form-check-label" for="om">{{ __('Override plan modules for this tenant') }}</label></div>
                    <div class="row mt-1">@foreach(config('erp.modules') as $k => $m)<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="modules[]" value="{{ $k }}" id="m{{ $k }}" @checked($c->moduleEnabled($k)) @disabled(!empty($m['core']))><label class="form-check-label small" for="m{{ $k }}">{{ __($m['label']) }} @if(! empty($m['new']))<span class="badge text-bg-success">{{ __('New') }}</span>@endif</label></div></div>@endforeach</div></div>
                @unless($c->trashed())<div class="col-12 text-end"><button class="btn btn-primary">{{ __('Save') }}</button></div>@endunless
            </form>
        </div>
        <div class="col-lg-5">
            @unless($c->trashed())
            <form method="post" action="{{ route('admin.companies.renew', $c) }}" class="card mb-3">@csrf <x-idem/>
                <div class="card-header">{{ __('Renew subscription') }}</div>
                <div class="card-body row g-2 align-items-end">
                    <div class="col-6"><select name="plan_id" class="form-select">@foreach($plans as $p)<option value="{{ $p->id }}" @selected($c->plan_id === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                    <div class="col-6"><select name="billing_cycle" class="form-select"><option value="monthly">{{ __('Monthly') }}</option><option value="yearly">{{ __('Yearly') }}</option></select></div>
                    <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="paid" value="1" id="pd"><label class="form-check-label" for="pd">{{ __('Paid') }}</label></div></div>
                    <div class="col-6"><button class="btn btn-success w-100">{{ __('Renew') }}</button></div>
                </div>
            </form>
            <form method="post" action="{{ route('admin.clients.extend-trial', $c) }}" class="card">@csrf
                <div class="card-header">{{ __('Extend trial') }}</div>
                <div class="card-body d-flex gap-2">
                    <select name="days" class="form-select">@foreach([7, 14, 30] as $d)<option value="{{ $d }}">+{{ $d }} {{ __('days') }}</option>@endforeach</select>
                    <button class="btn btn-outline-primary text-nowrap">{{ __('Extend') }}</button>
                </div>
                <div class="card-footer small text-body-secondary">{{ __('Trial ends') }}: {{ $c->trial_ends_at?->format('Y-m-d') ?? '—' }}</div>
            </form>
            @endunless
        </div>
    </div>

@elseif($tab === 'users')
    <div class="table-responsive"><table class="table table-erp align-middle mb-0">
        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Role') }}</th><th>{{ __('Last login') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @php setPermissionsTeamId($c->id); @endphp
        @forelse($users as $u)
            <tr class="{{ $u->is_active ? '' : 'opacity-50' }}">
                <td>{{ $u->name }} @if($u->hasTwoFactor())<i class="bi bi-shield-lock text-success" title="2FA"></i>@endif</td>
                <td dir="ltr" class="text-end">{{ $u->email }}</td>
                <td class="small">{{ $u->getRoleNames()->map(fn ($r) => __($r))->implode('، ') ?: '—' }}</td>
                <td class="small text-body-secondary">{{ $u->last_login_at?->diffForHumans() ?? __('Never') }} @if($u->last_login_ip)<div dir="ltr" class="text-end">{{ $u->last_login_ip }}</div>@endif</td>
                <td>{!! status_badge($u->is_active ? 'active' : 'inactive') !!}</td>
                <td class="text-end text-nowrap">
                    @unless($c->trashed())
                    <form method="post" action="{{ route('admin.clients.users.reset', [$c, $u]) }}" class="d-inline" data-confirm="{{ __('Generate a new temporary password for :e? The user will be signed out.', ['e' => $u->email]) }}">@csrf
                        <button class="btn btn-sm btn-outline-secondary" title="{{ __('Reset password') }}"><i class="bi bi-key"></i> {{ __('Reset password') }}</button>
                    </form>
                    <form method="post" action="{{ route('admin.clients.users.toggle', [$c, $u]) }}" class="d-inline">@csrf
                        <button class="btn btn-sm {{ $u->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $u->is_active ? __('Disable') : __('Enable') }}</button>
                    </form>
                    @endunless
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No records found.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <h6 class="mt-4">{{ __('Login History') }}</h6>
    <ul class="list-group list-group-flush small">
        @forelse($logins as $l)
            <li class="list-group-item d-flex gap-2"><i class="bi {{ $l->successful ? 'bi-check-circle text-success' : 'bi-x-circle text-danger' }}"></i>
                <span>{{ $l->email }}</span><span class="text-body-secondary" dir="ltr">{{ $l->ip_address }}</span><span class="ms-auto text-body-secondary">{{ $l->created_at?->format('Y-m-d H:i') }}</span></li>
        @empty
            <li class="list-group-item text-body-secondary">—</li>
        @endforelse
    </ul>

@elseif($tab === 'invoices')
    @unless($c->trashed())<div class="p-2 border-bottom text-end"><a class="btn btn-sm btn-primary" href="{{ route('admin.billing.create', ['company_id' => $c->id]) }}"><i class="bi bi-plus-lg"></i> {{ __('New invoice') }}</a></div>@endunless
    <div class="table-responsive"><table class="table table-erp align-middle mb-0">
        <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Period') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($invoices as $i)
            <tr>
                <td><a class="font-monospace fw-semibold" href="{{ route('admin.billing.show', $i->id) }}">{{ $i->number }}</a><div class="small text-body-secondary">{{ $i->issue_date?->format('Y-m-d') }}</div></td>
                <td>@if($i->period_start){{ $i->period_start->format('Y-m-d') }} → {{ $i->period_end?->format('Y-m-d') }}@else — @endif</td>
                <td class="text-end font-monospace">{{ money($i->amount) }}</td>
                <td>@php($ist = $i->stage())<span class="badge text-bg-{{ ['paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'secondary'][$ist] ?? 'warning' }}">{{ status_label($ist) }}</span> @if($i->paid_at)<span class="small text-body-secondary">{{ $i->paid_at->format('Y-m-d') }}</span>@endif</td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.billing.print', $i->id) }}" target="_blank" rel="noopener" aria-label="{{ __('Print') }}" title="{{ __('Print') }}"><i class="bi bi-printer"></i></a>
                    @if($i->status === 'unpaid' && ! $c->trashed())
                        <form method="post" action="{{ route('admin.clients.invoices.paid', [$c, $i->id]) }}" data-confirm="{{ __('Mark this invoice as paid?') }}">@csrf<button class="btn btn-sm btn-outline-success"><i class="bi bi-cash-coin"></i> {{ __('Mark paid') }}</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No invoices.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>

@else
    <ul class="list-group list-group-flush">
        @forelse($activity as $a)
            <li class="list-group-item d-flex gap-2 small">
                <i class="bi bi-dot"></i>
                <span class="fw-semibold">{{ __(\Illuminate\Support\Str::headline(str_replace(['saas.', '.'], ['', ' '], $a->event))) }}</span>
                <span class="text-body-secondary">{{ $a->user?->name ?? __('System') }}</span>
                <span class="ms-auto text-body-secondary">{{ $a->created_at?->format('Y-m-d H:i') }}</span>
            </li>
        @empty
            <li class="list-group-item text-body-secondary">{{ __('No history.') }}</li>
        @endforelse
    </ul>
@endif

</div>
</div>
@endsection
