@extends('layouts.app')
@section('title', $c->name)
@section('content')
<x-page-header :title="$c->name" icon="bi-person" :breadcrumbs="[__('Customers') => route('crud.index', 'customers'), $c->name]"
    :subtitle="e($c->code).' · <span dir=\'ltr\'>'.e($c->phone).'</span> · '.status_label($c->type).' · '.status_badge($c->classification)">
    @can('customers.update')<a href="{{ route('crud.edit', ['customers', $c->id]) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>@endcan
    <a href="{{ route('customers.statement', $c) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-file-text"></i> {{ __('Statement') }}</a>
    @can('reception.create')<a href="{{ route('reception.create', ['customer_id' => $c->id]) }}" class="btn btn-primary"><i class="bi bi-box-arrow-in-down"></i> {{ __('Receive vehicle') }}</a>@endcan
    @can('payments.create')<a href="{{ route('payments.receive', ['customer_id' => $c->id]) }}" class="btn btn-success"><i class="bi bi-wallet2"></i> {{ __('Receive payment') }}</a>@endcan
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card kpi"><div class="card-body"><div class="kpi-label">{{ __('Balance due') }}</div><div class="kpi-value {{ (float) $balance > 0 ? 'text-danger' : 'text-success' }}">{{ money($balance) }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card kpi"><div class="card-body"><div class="kpi-label">{{ __('Credit limit / available') }}</div><div class="kpi-value">{{ money($c->credit_limit, false) }} / {{ money($c->availableCredit(), false) }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card kpi"><div class="card-body"><div class="kpi-label">{{ __('Total purchases') }}</div><div class="kpi-value">{{ money($stats['spent']) }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card kpi"><div class="card-body"><div class="kpi-label">{{ __('Visits / last visit') }}</div><div class="kpi-value">{{ $stats['jobs'] }} <small class="fs-6 text-body-secondary">{{ $stats['last_visit'] ? \Illuminate\Support\Carbon::parse($stats['last_visit'])->format('Y-m-d') : '' }}</small></div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header d-flex"><span><i class="bi bi-car-front me-1"></i>{{ __('Vehicles') }}</span>@can('vehicles.create')<a href="{{ route('crud.create', 'vehicles') }}" class="ms-auto small">+ {{ __('Add vehicle') }}</a>@endcan</div>
            <div class="table-responsive"><table class="table table-erp mb-0"><tbody>
                @forelse($c->vehicles as $v)
                    <tr><td><a href="{{ route('vehicles.show', $v) }}" class="plate-badge text-decoration-none"><span class="flag">{{ $v->plate_country }}</span>{{ $v->plate() }}</a></td><td>{{ $v->title() }}</td><td class="small text-body-secondary">{{ $v->vin }}</td><td>{{ number_format($v->odometer) }} km</td><td class="small">{{ __('Next service') }}: {{ $v->next_service_date?->format('Y-m-d') ?? '—' }}</td></tr>
                @empty<tr><td class="text-body-secondary">{{ __('No vehicles.') }}</td></tr>@endforelse
            </tbody></table></div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clipboard-check me-1"></i>{{ __('Repair history') }}</div>
            <div class="table-responsive"><table class="table table-erp mb-0"><tbody>
                @forelse($jobs as $j)
                    <tr><td><a href="{{ route('job-cards.show', $j) }}">{{ $j->number }}</a></td><td>{{ $j->vehicle?->plate() }}</td><td>{{ $j->received_at->format('Y-m-d') }}</td><td class="small">{{ \Illuminate\Support\Str::limit($j->customer_complaint, 60) }}</td><td>{!! status_badge($j->status) !!}</td></tr>
                @empty<tr><td class="text-body-secondary">{{ __('No job cards.') }}</td></tr>@endforelse
            </tbody></table></div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-receipt me-1"></i>{{ __('Invoices') }}</div>
            <div class="table-responsive"><table class="table table-erp mb-0"><tbody>
                @forelse($invoices as $i)
                    <tr><td><a href="{{ route('invoices.show', $i) }}">{{ $i->number }}</a></td><td>{{ $i->issue_date->format('Y-m-d') }}</td><td class="text-end font-monospace">{{ money($i->total, false) }}</td><td class="text-end font-monospace">{{ money($i->balanceDue(), false) }}</td><td>{!! status_badge($i->status) !!}</td></tr>
                @empty<tr><td class="text-body-secondary">{{ __('No invoices.') }}</td></tr>@endforelse
            </tbody></table></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-body small">
            <div><b>{{ __('Email') }}:</b> {{ $c->email ?? '—' }}</div>
            <div><b>{{ __('Address') }}:</b> {{ $c->address }} {{ $c->city }}</div>
            <div><b>{{ __('C.R. / VAT') }}:</b> {{ $c->cr_number ?? '—' }} / {{ $c->vat_number ?? '—' }}</div>
            <div><b>{{ __('Group') }}:</b> {{ $c->group?->name ?? '—' }} · {{ __('Price level') }}: {{ status_label($c->price_level) }} · {{ __('Discount') }}: {{ qty($c->discount_percent) }}%</div>
            <div><b>{{ __('Payment terms') }}:</b> {{ $c->payment_terms_days }} {{ __('days') }}</div>
            @if($c->notes)<div class="mt-2 text-body-secondary">{{ $c->notes }}</div>@endif
        </div></div>
        @can('customers.update')
        <div class="card mb-3"><div class="card-header"><i class="bi bi-door-open me-1"></i>{{ __('Customer portal') }}</div>
            <form method="post" action="{{ route('customers.portal', $c) }}" class="card-body">@csrf <x-idem/>
                @if($c->portalUser)<div class="small mb-2 text-success"><i class="bi bi-check-circle"></i> {{ __('Active') }}: {{ $c->portalUser->email }}</div>@endif
                <div class="input-group input-group-sm"><input type="email" name="email" class="form-control" value="{{ $c->portalUser?->email ?? $c->email }}" required>
                    <button class="btn btn-outline-primary">{{ $c->portalUser ? __('Reset password') : __('Create access') }}</button></div>
            </form>
        </div>
        @endcan
        <div class="card mb-3"><div class="card-header">{{ __('Quotations') }}</div><ul class="list-group list-group-flush">
            @forelse($quotations as $q)<li class="list-group-item d-flex small"><a href="{{ route('quotations.show', $q) }}">{{ $q->number }}</a><span class="ms-auto me-2 font-monospace">{{ money($q->total, false) }}</span>{!! status_badge($q->status) !!}</li>
            @empty<li class="list-group-item small text-body-secondary">—</li>@endforelse
        </ul></div>
        <div class="card mb-3"><div class="card-header d-flex">{{ __('Complaints') }}<a href="{{ route('crud.create', 'complaints') }}" class="ms-auto small">+</a></div><ul class="list-group list-group-flush">
            @forelse($complaints as $cp)<li class="list-group-item small d-flex">{{ $cp->subject }}<span class="ms-auto">{!! status_badge($cp->status) !!}</span></li>
            @empty<li class="list-group-item small text-body-secondary">—</li>@endforelse
        </ul></div>
        <x-attachments :model="$c" :title="__('Documents')"/>
    </div>
</div>
@endsection
