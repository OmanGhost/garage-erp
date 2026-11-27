@extends('layouts.app')
@section('title', $m->number)
@section('content')
<x-page-header :title="__('Membership').' '.$m->number" icon="bi-person-vcard" :breadcrumbs="[__('Memberships') => route('crud.index', 'memberships'), $m->number]"
    :subtitle="e($m->customer?->name).' · '.e($m->plan?->displayName()).' · '.status_badge($m->status)">
    <a href="{{ route('memberships.card', $m) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print membership card') }}</a>
    @if($m->invoice)<a href="{{ route('invoices.show', $m->invoice) }}" class="btn btn-outline-secondary"><i class="bi bi-receipt"></i> {{ __('Invoice') }}</a>@endif
</x-page-header>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-body doc-meta">
            <div><span>{{ __('Customer') }}</span><a href="{{ route('customers.show', $m->customer) }}">{{ $m->customer?->name }}</a></div>
            <div><span>{{ __('Vehicle') }}</span>{{ $m->vehicle?->label() ?? __('All vehicles of the customer') }}</div>
            <div><span>{{ __('Validity') }}</span>{{ $m->starts_at?->format('Y-m-d') ?? '—' }} → {{ $m->ends_at?->format('Y-m-d') ?? '—' }}</div>
            <div><span>{{ __('Discounts') }}</span>{{ __('Parts') }} {{ rtrim(rtrim((string) $m->plan?->parts_discount, '0'), '.') }}% · {{ __('Labour') }} {{ rtrim(rtrim((string) $m->plan?->labor_discount, '0'), '.') }}%</div>
            @if($m->plan?->priority_booking)<div><span>{{ __('Priority booking') }}</span><i class="bi bi-check-circle-fill text-success"></i></div>@endif
            @if($m->plan?->roadside_calls)<div><span>{{ __('Roadside assistance calls') }}</span>{{ $m->roadsideLeft() }} / {{ $m->plan->roadside_calls }}</div>@endif
        </div></div>
        @if($m->status === 'pending')<div class="alert alert-warning small">{{ __('Becomes active when its invoice is posted.') }}</div>@endif
    </div>
    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-header fw-semibold">{{ __('Included services') }}</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead class="table-light"><tr><th>{{ __('Service') }}</th><th class="text-end">{{ __('Included') }}</th><th class="text-end">{{ __('Used') }}</th><th class="text-end">{{ __('Left') }}</th></tr></thead>
            <tbody>@forelse($m->balances as $b)<tr><td>{{ $b->service?->displayName() }}</td><td class="text-end">{{ $b->included }}</td><td class="text-end">{{ $b->used }}</td><td class="text-end fw-semibold {{ $b->left() ? 'text-success' : 'text-body-secondary' }}">{{ $b->left() }}</td></tr>
            @empty<tr><td colspan="4" class="text-body-secondary small">{{ __('Available after activation.') }}</td></tr>@endforelse</tbody></table></div>
        </div>
        <div class="card"><div class="card-header fw-semibold">{{ __('Use history') }}</div><ul class="list-group list-group-flush small">
            @forelse($m->usages->sortByDesc('id') as $u)<li class="list-group-item d-flex gap-2"><a href="{{ route('invoices.show', $u->invoice_id) }}">{{ $u->invoice?->displayNumber() }}</a>
                <span>{{ $u->service?->displayName() ?? __('Discount') }}</span><span class="badge {{ $u->kind === 'included' ? 'text-bg-success' : 'text-bg-info' }}">{{ $u->kind === 'included' ? __('Included') : __('Discount') }}</span>
                <span class="ms-auto font-monospace">{{ money($u->value, false) }}</span></li>
            @empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul></div>
    </div>
</div>
@endsection
