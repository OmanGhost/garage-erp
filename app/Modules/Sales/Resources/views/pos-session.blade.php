@extends('layouts.app')
@section('title', __('Cash session').' '.$s->number)
@section('content')
<x-page-header :title="__('Cash session').' '.$s->number" icon="bi-cash-stack" :breadcrumbs="[__('Cash Sessions') => route('crud.index', 'pos-sessions'), $s->number]"
    :subtitle="$s->register->name.' · '.$s->user->name.' · '.status_badge($s->status)">
    <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print') }}</button>
</x-page-header>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-6">{{ __('Opened') }}</dt><dd class="col-6">{{ $s->opened_at->format('Y-m-d H:i') }}</dd>
                <dt class="col-6">{{ __('Closed') }}</dt><dd class="col-6">{{ $s->closed_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                <dt class="col-6">{{ __('Opening cash') }}</dt><dd class="col-6 font-monospace">{{ money($s->opening_cash) }}</dd>
                <dt class="col-6">{{ __('Expected cash') }}</dt><dd class="col-6 font-monospace">{{ money($expected) }}</dd>
                <dt class="col-6">{{ __('Counted cash') }}</dt><dd class="col-6 font-monospace">{{ $s->counted_cash !== null ? money($s->counted_cash) : '—' }}</dd>
                <dt class="col-6">{{ __('Over / short') }}</dt><dd class="col-6 font-monospace {{ (float) $s->difference < 0 ? 'text-danger' : 'text-success' }}">{{ money($s->difference) }}</dd>
                @if($s->difference_reason)<dt class="col-6">{{ __('Reason for the difference') }}</dt><dd class="col-6">{{ $s->difference_reason }}</dd>@endif
                @if($s->notes)<dt class="col-6">{{ __('Notes') }}</dt><dd class="col-6">{{ $s->notes }}</dd>@endif
            </dl>
            <hr>
            <h6>{{ __('By payment method') }}</h6>
            @foreach($byMethod as $m)<div class="d-flex small"><span>{{ status_label($m->method) }} ({{ $m->direction === 'in' ? __('in') : __('out') }})</span><span class="ms-auto font-monospace">{{ money($m->total) }}</span></div>@endforeach
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card"><div class="table-responsive"><table class="table table-erp mb-0">
            <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Terms') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
            <tbody>@foreach($invoices as $i)<tr><td><a href="{{ route('invoices.show', $i) }}">{{ $i->displayNumber() }}</a></td><td>{{ $i->customer?->name }}</td><td>{{ status_label($i->payment_terms) }}</td><td>{!! status_badge($i->status) !!}</td><td class="text-end font-monospace">{{ money($i->total, false) }}</td></tr>@endforeach</tbody>
            <tfoot><tr class="fw-bold"><td colspan="4">{{ __('Total sales') }}</td><td class="text-end font-monospace">{{ money($invoices->whereNotIn('status', ['held'])->sum('total')) }}</td></tr></tfoot>
        </table></div></div>
    </div>
</div>
@endsection
