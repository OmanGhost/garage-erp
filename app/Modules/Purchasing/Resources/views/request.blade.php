@extends('layouts.app')
@section('title', $pr->number)
@section('content')
<x-page-header :title="__('Purchase request').' '.$pr->number" icon="bi-journal-plus" :breadcrumbs="[__('Purchase Requests') => route('crud.index', 'purchase-requests'), $pr->number]"
    :subtitle="__('By').' '.e($pr->requester?->name).' · '.status_badge($pr->priority).' · '.status_badge($pr->status)">
    @if($pr->status === 'submitted')@can('purchase_requests.approve')
        <form method="post" action="{{ route('purchase-requests.decide', [$pr, 'approve']) }}">@csrf <x-idem/><button class="btn btn-success">{{ __('Approve') }}</button></form>
        <form method="post" action="{{ route('purchase-requests.decide', [$pr, 'reject']) }}">@csrf <x-idem/><button class="btn btn-outline-danger">{{ __('Reject') }}</button></form>
    @endcan @endif
    @if($pr->status === 'approved')@can('rfqs.create')<a href="{{ route('rfqs.create', ['purchase_request_id' => $pr->id]) }}" class="btn btn-primary"><i class="bi bi-envelope-paper"></i> {{ __('Request quotations (RFQ)') }}</a>@endcan @endif
</x-page-header>
<div class="card"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Qty') }}</th><th class="text-end">{{ __('Est. cost') }}</th><th>{{ __('Available now') }}</th></tr></thead>
    <tbody>@foreach($pr->items as $i)<tr><td>{{ $i->description }} <small class="text-body-secondary">{{ $i->part?->sku }}</small></td><td>{{ qty($i->qty) }}</td><td class="text-end font-monospace">{{ money($i->estimated_cost, false) }}</td><td>{{ $i->part ? qty($i->part->available()) : '—' }}</td></tr>@endforeach</tbody>
</table>
<div class="card-body small">@if($pr->jobCard){{ __('Job card') }}: <a href="{{ route('job-cards.show', $pr->jobCard) }}">{{ $pr->jobCard->number }}</a> · @endif {{ __('Required by') }}: {{ $pr->required_date?->format('Y-m-d') ?? '—' }} · {{ $pr->notes }}
    @if($pr->approver)<div>{{ __('Decision by') }} {{ $pr->approver->name }} · {{ $pr->approved_at?->format('Y-m-d H:i') }}</div>@endif</div></div>
@endsection
