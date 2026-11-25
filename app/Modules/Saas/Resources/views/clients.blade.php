@extends('layouts.admin')
@section('title', __('Clients'))
@section('content')
@php
    $filter = request('filter', 'all');
    $labels = ['all' => __('All'), 'active' => __('Active'), 'trial' => __('On trial'), 'expiring' => __('Expiring in 14 days'),
        'expired' => __('Expired'), 'suspended' => __('Suspended'), 'archived' => __('Archived')];
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h4 class="mb-0"><i class="bi bi-people"></i> {{ __('Clients') }}</h4>
    <div class="ms-auto d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download"></i> {{ __('Export') }}</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}">Excel</a></li>
                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">CSV</a></li>
            </ul>
        </div>
        <a href="{{ route('admin.companies.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New client') }}</a>
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach([[__('Clients'), $counts['all'], 'bi-buildings', 'primary'], [__('Active'), $counts['active'], 'bi-check-circle', 'success'],
              [__('On trial'), $counts['trial'], 'bi-hourglass-split', 'info'], [__('Suspended'), $counts['suspended'], 'bi-pause-circle', 'danger'],
              [__('Unpaid subscription invoices'), money($counts['unpaid'], false).' OMR', 'bi-receipt', 'warning']] as [$l, $v, $icon, $color])
        <div class="col-6 col-lg"><div class="card kpi h-100"><div class="card-body d-flex align-items-center gap-3">
            <span class="fs-3 text-{{ $color }}"><i class="bi {{ $icon }}"></i></span>
            <div><div class="kpi-label">{{ $l }}</div><div class="kpi-value">{{ $v }}</div></div>
        </div></div></div>
    @endforeach
</div>

<div class="card">
    <div class="card-body border-bottom d-flex flex-wrap gap-2 align-items-center">
        <div class="btn-group btn-group-sm flex-wrap" role="group">
            @foreach($labels as $k => $l)
                <a href="{{ request()->fullUrlWithQuery(['filter' => $k, 'page' => null]) }}" class="btn {{ $filter === $k ? 'btn-dark' : 'btn-outline-secondary' }}">
                    {{ $l }} @isset($counts[$k])<span class="badge text-bg-light">{{ $counts[$k] }}</span>@endisset
                </a>
            @endforeach
        </div>
        <form method="get" class="d-flex flex-wrap gap-2 ms-auto">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input class="form-control form-control-sm" style="width:230px" name="q" value="{{ request('q') }}" placeholder="{{ __('Name, email, phone, C.R....') }}">
            <select name="plan" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
                <option value="">{{ __('All plans') }}</option>
                @foreach($plans as $p)<option value="{{ $p->id }}" @selected((string) request('plan') === (string) $p->id)>{{ $p->name }}</option>@endforeach
            </select>
            <select name="sort" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
                @foreach(['' => __('Newest first'), 'name' => __('Name'), 'ends' => __('Subscription ends'), 'users' => __('Most users')] as $k => $l)
                    <option value="{{ $k }}" @selected(request('sort', '') === $k)>{{ $l }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="table-responsive"><table class="table table-erp table-hover mb-0 align-middle">
        <thead><tr>
            <th>{{ __('Client') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Status') }}</th>
            <th>{{ __('Subscription ends') }}</th><th class="text-center">{{ __('Users') }}</th><th>{{ __('Last login') }}</th><th></th>
        </tr></thead>
        <tbody>
        @forelse($clients as $c)
            @php
                $ends = $c->subscription_status === 'trial' ? $c->trial_ends_at : $c->subscription_ends_at;
                $days = $ends ? (int) now()->startOfDay()->diffInDays($ends->copy()->startOfDay(), false) : null;
                $state = $c->trashed() ? 'archived' : (! $c->is_active ? 'suspended' : ($c->subscriptionActive() ? $c->subscription_status : 'expired'));
                $last = $lastLogins[$c->id] ?? null;
            @endphp
            <tr class="{{ $c->trashed() ? 'opacity-75' : '' }}">
                <td>
                    <a href="{{ route('admin.clients.show', $c->id) }}" class="fw-semibold">{{ $c->displayName() }}</a>
                    <div class="small text-body-secondary">{{ $c->slug }} @if($c->cr_number)· {{ __('C.R.') }} {{ $c->cr_number }}@endif @if($c->city)· {{ $c->city }}@endif</div>
                </td>
                <td class="small">
                    @if($c->contact_name)<div>{{ $c->contact_name }}</div>@endif
                    @if($c->contact_phone ?: $c->phone)<div dir="ltr" class="text-body-secondary text-end">{{ $c->contact_phone ?: $c->phone }}</div>@endif
                    @if($c->email)<div class="text-body-secondary">{{ $c->email }}</div>@endif
                </td>
                <td>{{ $c->plan?->name ?? '—' }}</td>
                <td>{!! status_badge($state) !!}</td>
                <td>
                    {{ $ends?->format('Y-m-d') ?? '—' }}
                    @if($days !== null && ! $c->trashed())
                        <div class="small {{ $days < 0 ? 'text-danger' : ($days <= 14 ? 'text-warning' : 'text-body-secondary') }}">
                            {{ $days < 0 ? __(':n days ago', ['n' => abs($days)]) : __('in :n days', ['n' => $days]) }}
                        </div>
                    @endif
                </td>
                <td class="text-center">{{ $c->users_count }} <span class="text-body-tertiary small">/ {{ $c->plan?->max_users ?? '∞' }}</span></td>
                <td class="small text-body-secondary">{{ $last ? \Illuminate\Support\Carbon::parse($last)->diffForHumans() : __('Never') }}</td>
                <td class="text-end text-nowrap">
                    @if($c->trashed())
                        <form method="post" action="{{ route('admin.clients.restore', $c->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success"><i class="bi bi-arrow-counterclockwise"></i> {{ __('Restore') }}</button></form>
                    @else
                        <a href="{{ route('admin.clients.show', $c->id) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Open') }}"><i class="bi bi-folder2-open"></i></a>
                        <form method="post" action="{{ route('admin.companies.enter', $c) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-primary" title="{{ __('Enter tenant') }}"><i class="bi bi-box-arrow-in-right"></i></button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-body-secondary py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>{{ __('No records found.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($clients->hasPages())<div class="card-body">{{ $clients->links() }}</div>@endif
</div>
@endsection
