@extends('layouts.app')
@section('title', __('Subscription'))
@section('content')
<x-page-header :title="__('Subscription & usage')" icon="bi-stars" :breadcrumbs="[__('Administration'), __('Subscription')]"/>
<div class="row g-3">
    <div class="col-lg-5"><div class="card"><div class="card-body">
        <div class="fs-4 fw-bold">{{ $company->plan?->name ?? '—' }}</div>
        <div class="mb-2">{!! status_badge($company->subscriptionActive() ? $company->subscription_status : 'expired') !!}</div>
        <div class="small">{{ __('Trial ends') }}: {{ $company->trial_ends_at?->format('Y-m-d') ?? '—' }} · {{ __('Subscription ends') }}: {{ $company->subscription_ends_at?->format('Y-m-d') ?? '—' }}</div>
        <hr>
        @foreach([['users', __('Users'), $company->plan?->max_users], ['branches', __('Branches'), $company->plan?->max_branches], ['warehouses', __('Warehouses'), $company->plan?->max_warehouses], ['storage_mb', __('Storage (MB)'), $company->plan?->max_storage_mb]] as [$k, $l, $max])
            <div class="small d-flex">{{ $l }}<span class="ms-auto">{{ $usage[$k] }} / {{ $max ?? '∞' }}</span></div>
            <div class="progress mb-2" style="height:6px"><div class="progress-bar {{ $max && $usage[$k] / $max > .9 ? 'bg-danger' : '' }}" style="width:{{ $max ? min(100, $usage[$k] * 100 / $max) : 5 }}%"></div></div>
        @endforeach
        <hr><div class="small">{{ __('Enabled modules') }}:</div>
        <div class="d-flex flex-wrap gap-1 mt-1">@foreach(config('erp.modules') as $k => $m)<span class="badge {{ $company->moduleEnabled($k) ? 'text-bg-success' : 'text-bg-light text-decoration-line-through' }}">{{ __($m['label']) }}</span>@endforeach</div>
    </div></div></div>
    <div class="col-lg-7">
        <div class="row g-2 mb-3">@foreach($plans as $p)<div class="col-md-4"><div class="card h-100 {{ $company->plan_id === $p->id ? 'border-primary' : '' }}"><div class="card-body text-center"><div class="fw-bold">{{ $p->name }}</div><div class="fs-5">{{ money($p->price_monthly) }}</div><div class="small text-body-secondary">/ {{ __('month') }}</div>@if($p->allow_custom_domain)<div class="small text-success mt-1"><i class="bi bi-globe2"></i> {{ __('Custom domain') }}</div>@endif</div></div></div>@endforeach</div>
        <div class="card"><div class="card-header">{{ __('History') }}</div><ul class="list-group list-group-flush small">
            @forelse($subscriptions as $s)<li class="list-group-item d-flex">{{ $s->plan->name }} · {{ status_label($s->billing_cycle) }} · {{ $s->starts_at->format('Y-m-d') }} → {{ $s->ends_at?->format('Y-m-d') }}<span class="ms-auto">{{ money($s->amount) }}</span></li>
            @empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul><div class="card-footer small text-body-secondary">{{ __('To upgrade or renew, contact the platform administrator.') }}</div></div>
        <div class="card mt-3"><div class="card-header"><i class="bi bi-receipt"></i> {{ __('Invoices') }}</div>
            <div class="table-responsive"><table class="table align-middle mb-0 small">
                <thead class="table-light"><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($invoices as $i)
                    @php($ist = $i->stage())
                    <tr><td class="font-monospace">{{ $i->number }}</td><td>{{ $i->issue_date?->format('Y-m-d') }}</td>
                        <td class="{{ $ist === 'overdue' ? 'text-danger fw-semibold' : '' }}">{{ $i->due_date?->format('Y-m-d') }}</td>
                        <td class="text-end font-monospace">{{ money($i->amount) }}</td>
                        <td><span class="badge text-bg-{{ ['paid' => 'success', 'overdue' => 'danger'][$ist] ?? 'warning' }}">{{ status_label($ist) }}</span></td>
                        <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="{{ route('billing.invoice', $i->id) }}" target="_blank" rel="noopener" aria-label="{{ __('Print') }}" title="{{ __('Print') }}"><i class="bi bi-printer"></i></a>
                            <a class="btn btn-sm btn-outline-danger" href="{{ route('billing.invoice', [$i->id, 'pdf']) }}" target="_blank" rel="noopener">PDF</a></td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No invoices.') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        @include('system.partials.domain')
    </div>
</div>
@endsection
