@extends('layouts.app')
@section('title', $v->plate())
@section('content')
<x-page-header :title="$v->plate().' — '.$v->title()" icon="bi-car-front" :breadcrumbs="[__('Vehicles') => route('crud.index', 'vehicles'), $v->plate()]"
    :subtitle="'VIN: '.e($v->vin ?? '—').' · '.status_label($v->vehicle_type).' · '.number_format($v->odometer).' km'">
    @can('vehicles.update')<a href="{{ route('crud.edit', ['vehicles', $v->id]) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>@endcan
    @can('reception.create')<a href="{{ route('reception.create', ['customer_id' => $v->customer_id]) }}" class="btn btn-primary"><i class="bi bi-box-arrow-in-down"></i> {{ __('Receive vehicle') }}</a>@endcan
</x-page-header>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3"><div class="card-header"><i class="bi bi-clock-history me-1"></i>{{ __('Maintenance & repair history') }}</div>
            <div class="table-responsive"><table class="table table-erp mb-0">
                <thead><tr><th>{{ __('Job card') }}</th><th>{{ __('Date') }}</th><th>{{ __('Odometer') }}</th><th>{{ __('Work done') }}</th><th class="text-end">{{ __('Invoiced') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>@forelse($jobs as $j)
                    <tr><td><a href="{{ route('job-cards.show', $j) }}">{{ $j->number }}</a></td><td>{{ $j->received_at->format('Y-m-d') }}</td><td>{{ number_format($j->odometer_in) }}</td>
                        <td class="small">{{ $j->tasks->where('status', 'done')->pluck('description')->implode('، ') ?: \Illuminate\Support\Str::limit($j->customer_complaint, 60) }}</td>
                        <td class="text-end font-monospace">{{ money($j->invoices->whereIn('status', ['posted', 'partially_paid', 'paid'])->sum('total'), false) }}</td><td>{!! status_badge($j->status) !!}</td></tr>
                @empty<tr><td colspan="6" class="text-body-secondary">{{ __('No history.') }}</td></tr>@endforelse</tbody>
            </table></div></div>
        <div class="card mb-3"><div class="card-header"><i class="bi bi-gear me-1"></i>{{ __('Replaced parts') }}</div>
            <div class="table-responsive"><table class="table table-sm table-erp mb-0">
                <thead><tr><th>SKU</th><th>{{ __('Part') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Job') }}</th><th>{{ __('Odometer') }}</th><th>{{ __('Date') }}</th></tr></thead>
                <tbody>@forelse($replaced as $r)<tr><td>{{ $r->sku }}</td><td>{{ app()->getLocale() === 'en' && $r->name_en ? $r->name_en : $r->name_ar }}</td><td>{{ qty($r->qty_issued - $r->qty_returned) }}</td><td>{{ status_label($r->part_condition) }}</td><td>{{ $r->number }}</td><td>{{ number_format($r->odometer_in) }}</td><td>{{ $r->issued_at ? substr($r->issued_at, 0, 10) : '' }}</td></tr>
                @empty<tr><td colspan="7" class="text-body-secondary">—</td></tr>@endforelse</tbody>
            </table></div></div>
        <x-attachments :model="$v" :title="__('Vehicle photos & documents')"/>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-body small">
            <div><b>{{ __('Owner') }}:</b> <a href="{{ route('customers.show', $v->customer) }}">{{ $v->customer?->name }}</a></div>
            <div><b>{{ __('Engine') }}:</b> {{ $v->engine_type }} {{ $v->engine_capacity }} · {{ $v->engine_number }}</div>
            <div><b>{{ __('Transmission / fuel') }}:</b> {{ status_label($v->transmission) }} / {{ status_label($v->fuel_type) }}</div>
            <div><b>{{ __('Color') }}:</b> {{ $v->color }}</div>
            <div><b>{{ __('Registration expiry') }}:</b> {{ $v->registration_expiry?->format('Y-m-d') ?? '—' }}</div>
            <div><b>{{ __('Insurance') }}:</b> {{ $v->insuranceCompany?->name ?? '—' }} {{ $v->insurance_policy_number }} {{ $v->insurance_expiry?->format('Y-m-d') }}</div>
            <div><b>{{ __('Next service') }}:</b> {{ $v->next_service_date?->format('Y-m-d') ?? '—' }} / {{ $v->next_service_odometer ? number_format($v->next_service_odometer).' km' : '' }}</div>
            @if($v->fleet)<div><b>{{ __('Fleet') }}:</b> {{ $v->fleet->name }}</div>@endif
        </div></div>
        @can('vehicles.update')
        <div class="card mb-3"><div class="card-header">{{ __('Transfer ownership') }}</div>
            <form method="post" action="{{ route('vehicles.transfer', $v) }}" class="card-body" data-confirm="{{ __('Transfer the vehicle to the selected customer?') }}">@csrf <x-idem/>
                <x-lookup name="to_customer_id" type="customers" size="sm" required/>
                <input name="notes" class="form-control form-control-sm mt-2" placeholder="{{ __('Notes') }}">
                <button class="btn btn-sm btn-outline-primary mt-2 w-100">{{ __('Transfer') }}</button>
            </form>
        </div>
        @endcan
        <div class="card mb-3"><div class="card-header">{{ __('Ownership history') }}</div><ul class="list-group list-group-flush small">
            @forelse($v->ownerships as $o)<li class="list-group-item">{{ $o->changed_at->format('Y-m-d') }}: {{ $o->fromCustomer?->name ?? '—' }} → {{ $o->toCustomer?->name }}</li>
            @empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul></div>
        <div class="card mb-3"><div class="card-header">{{ __('Accidents') }}</div><ul class="list-group list-group-flush small">
            @forelse($v->accidents as $a)<li class="list-group-item">{{ $a->accident_date->format('Y-m-d') }} — {{ $a->location }} {{ \Illuminate\Support\Str::limit($a->description, 60) }}</li>
            @empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul></div>
        <div class="card"><div class="card-header">{{ __('Warranties') }}</div><ul class="list-group list-group-flush small">
            @forelse($v->warranties as $w)<li class="list-group-item d-flex">{{ $w->description }}<span class="ms-auto">{{ $w->end_date->format('Y-m-d') }} {!! status_badge($w->status) !!}</span></li>
            @empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul></div>
    </div>
</div>
@endsection
