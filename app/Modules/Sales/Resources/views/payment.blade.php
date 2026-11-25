@extends('layouts.app')
@section('title', $p->number)
@section('content')
<x-page-header :title="($p->direction === 'in' ? __('Receipt voucher') : __('Payment voucher')).' '.$p->number" icon="bi-wallet2" :breadcrumbs="[__('Payments') => route('crud.index', 'payments'), $p->number]" :subtitle="status_badge($p->status)">
    <a href="{{ route('payments.show', [$p, 'print' => 1]) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print') }}</a>
    @if($p->status === 'posted')
        @can('payments.approve')
        <form method="post" action="{{ route('payments.cancel', $p) }}" class="d-flex gap-2" data-confirm="{{ __('Cancel and reverse this payment?') }}">@csrf <x-idem/>
            <input name="reason" class="form-control" placeholder="{{ __('Reason') }}" required><button class="btn btn-outline-danger text-nowrap">{{ __('Cancel payment') }}</button>
        </form>
        @endcan
    @endif
</x-page-header>
<div class="row g-3">
    <div class="col-lg-5"><div class="card"><div class="card-body">
        <dl class="row mb-0">
            <dt class="col-5">{{ __('Party') }}</dt><dd class="col-7">{{ $p->party?->name }}</dd>
            <dt class="col-5">{{ __('Amount') }}</dt><dd class="col-7 fs-5 fw-bold font-monospace">{{ money($p->amount) }}</dd>
            <dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $p->date->format('Y-m-d') }}</dd>
            <dt class="col-5">{{ __('Method') }}</dt><dd class="col-7">{{ status_label($p->method) }}</dd>
            <dt class="col-5">{{ __('Cash / bank') }}</dt><dd class="col-7">{{ $p->bankAccount->name }}</dd>
            <dt class="col-5">{{ __('Reference') }}</dt><dd class="col-7">{{ $p->reference }} {{ $p->cheque_number }}</dd>
            <dt class="col-5">{{ __('By') }}</dt><dd class="col-7">{{ $p->creator?->name }}</dd>
            @if($p->journal)<dt class="col-5">{{ __('Journal') }}</dt><dd class="col-7"><a href="{{ route('journal.show', $p->journal) }}">{{ $p->journal->number }}</a></dd>@endif
        </dl>
    </div></div></div>
    <div class="col-lg-7"><div class="card"><div class="card-header">{{ __('Allocations') }}</div><ul class="list-group list-group-flush">
        @forelse($p->allocations as $a)<li class="list-group-item d-flex">{{ $a->allocatable?->number }}<span class="ms-auto font-monospace">{{ money($a->amount) }}</span></li>
        @empty<li class="list-group-item text-body-secondary small">{{ __('Unallocated (on account).') }}</li>@endforelse
    </ul></div></div>
</div>
@endsection
