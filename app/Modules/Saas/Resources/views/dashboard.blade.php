@extends('layouts.admin')
@section('title', __('Tenants'))
@section('content')
<div class="d-flex mb-3"><h4 class="mb-0">{{ __('SaaS dashboard') }}</h4><a href="{{ route('admin.companies.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('New client') }}</a></div>
<div class="row g-3 mb-3">
    @foreach([[__('Tenants'), $stats['tenants']], [__('Active'), $stats['active']], [__('On trial'), $stats['trial']], [__('Expiring in 14 days'), $stats['expiring']], [__('Users'), $stats['users']], [__('MRR'), money($mrr, false).' OMR']] as [$l, $v])
        <div class="col-6 col-md-2"><div class="card kpi"><div class="card-body"><div class="kpi-label">{{ $l }}</div><div class="kpi-value">{{ $v }}</div></div></div></div>
    @endforeach
</div>
<div class="card mb-3"><div class="card-header d-flex align-items-center"><span class="fw-semibold"><i class="bi bi-stars text-success"></i> {{ __('New features — clients with access') }}</span><a href="{{ route('admin.plans') }}" class="ms-auto small">{{ __('Manage plans') }}</a></div>
    <div class="card-body row g-3">
        @foreach($adoption as $k => $a)
            @php $pct = $activeCount ? round($a['n'] * 100 / $activeCount) : 0; @endphp
            <div class="col-md-6 col-xl-4"><div class="d-flex small mb-1"><span>{{ __($a['label']) }}</span><span class="ms-auto fw-semibold">{{ $a['n'] }} / {{ $activeCount }}</span></div>
                <div class="progress" style="height:8px" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __($a['label']) }}"><div class="progress-bar bg-success" style="width:{{ $pct }}%"></div></div></div>
        @endforeach
    </div>
</div>
<div class="card"><div class="card-header d-flex align-items-center"><span class="fw-semibold">{{ __('Latest clients') }}</span><a href="{{ route('admin.clients.index') }}" class="ms-auto small">{{ __('View all clients') }} <i class="bi bi-arrow-left-short"></i></a></div><div class="table-responsive"><table class="table table-erp mb-0 align-middle">
    <thead><tr><th>{{ __('Company') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Status') }}</th><th>{{ __('Ends') }}</th><th>{{ __('Users') }}</th><th>{{ __('Branches') }}</th><th>{{ __('Domain / store') }}</th><th></th></tr></thead>
    <tbody>@foreach($companies as $c)
        <tr class="{{ $c->is_active ? '' : 'table-danger' }}"><td><a href="{{ route('admin.clients.show', $c->id) }}" class="fw-semibold">{{ $c->displayName() }}</a><div class="small text-body-secondary">{{ $c->cr_number }} · {{ $c->email }}</div></td>
            <td>{{ $c->plan?->name ?? '—' }}</td><td>{!! status_badge($c->subscriptionActive() ? $c->subscription_status : 'expired') !!}</td>
            <td>{{ ($c->subscription_status === 'trial' ? $c->trial_ends_at : $c->subscription_ends_at)?->format('Y-m-d') ?? '—' }}</td>
            <td>{{ $c->users_count }}</td><td>{{ $c->branches_count }}</td><td class="small">{{ $c->custom_domain }} <a href="{{ route('store.home', $c) }}" target="_blank">/shop/{{ $c->slug }}</a></td>
            <td><form method="post" action="{{ route('admin.companies.enter', $c) }}">@csrf<button class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-in-right"></i> {{ __('Enter') }}</button></form></td></tr>
    @endforeach</tbody>
</table></div></div>
@endsection
