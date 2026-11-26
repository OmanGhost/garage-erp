@extends('layouts.app')
@section('title', $si->number)
@section('content')
@php
    $open = in_array($si->status, ['posted', 'partially_paid', 'paid'], true);
    $balance = $open ? $si->balanceDue() : '0';
    $paidPct = (float) $si->total > 0 ? min(100, round((float) $si->paid_amount * 100 / (float) $si->total)) : 0;
    $overdue = $open && \App\Support\Money::isPositive($balance) && $si->due_date && $si->due_date->lt(today());
    $tone = ['draft' => 'secondary', 'posted' => 'primary', 'partially_paid' => 'warning', 'paid' => 'success', 'cancelled' => 'danger'][$si->status] ?? 'secondary';
    $sup = $si->supplier;
@endphp
<x-page-header :title="__('Supplier invoice').' '.$si->number" icon="bi-receipt" :breadcrumbs="[__('Supplier Invoices') => route('crud.index', 'supplier-invoices'), $si->number]">
    @if($si->status === 'draft')@can('supplier_invoices.approve')<form method="post" action="{{ route('supplier-invoices.post', $si) }}" data-confirm="{{ __('Post this supplier invoice?') }}">@csrf <x-idem/><button class="btn btn-success"><i class="bi bi-check2-circle"></i> {{ __('Post') }}</button></form>@endcan @endif
    @if(in_array($si->status, ['posted', 'partially_paid']))@can('payments.create')<a href="{{ route('payments.pay', ['supplier_id' => $si->supplier_id]) }}" class="btn btn-danger"><i class="bi bi-cash-stack"></i> {{ __('Pay') }}</a>@endcan @endif
</x-page-header>

<div class="card doc-hero mb-3 border-{{ $tone }}">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-lg-5">
                <div class="d-flex align-items-center gap-3">
                    <span class="doc-hero-icon bg-{{ $tone }}-subtle text-{{ $tone }}"><i class="bi bi-receipt"></i></span>
                    <div class="min-w-0">
                        <div class="small text-body-secondary">{{ __('Supplier invoice') }}@if($si->supplier_invoice_number) · {{ __('Ref') }} <bdi dir="ltr" class="font-monospace">{{ $si->supplier_invoice_number }}</bdi>@endif</div>
                        <div class="fs-4 fw-bold font-monospace text-truncate">{{ $si->number }}</div>
                        <div class="d-flex flex-wrap gap-1 mt-1">{!! status_badge($si->status) !!}@if($overdue)<span class="badge text-bg-danger"><i class="bi bi-alarm"></i> {{ __('Overdue') }}</span>@endif</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="doc-meta">
                    <div><span>{{ __('Supplier') }}</span><a class="fw-semibold" href="{{ route('suppliers.show', $sup) }}">{{ $sup->name }}</a></div>
                    @if($sup->phone)<div><span>{{ __('Phone') }}</span><bdi dir="ltr">{{ $sup->phone }}</bdi></div>@endif
                    @if($sup->vat_number)<div><span>{{ __('VAT number') }}</span><bdi dir="ltr">{{ $sup->vat_number }}</bdi></div>@endif
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="doc-meta">
                    <div><span>{{ __('Date') }}</span>{{ $si->invoice_date->format('Y-m-d') }}</div>
                    <div><span>{{ __('Due') }}</span><span class="{{ $overdue ? 'text-danger fw-semibold' : '' }}">{{ $si->due_date?->format('Y-m-d') ?? '—' }}</span></div>
                </div>
            </div>
            <div class="col-lg-2 text-lg-end">
                <div class="small text-body-secondary">{{ __('Total') }}</div>
                <div class="fs-3 fw-bold font-monospace lh-1">{{ money($si->total) }}</div>
                @if($open)
                    <div class="progress mt-2" style="height:6px" role="progressbar" aria-label="{{ __('Paid') }}" aria-valuenow="{{ $paidPct }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width:{{ $paidPct }}%"></div></div>
                    <div class="small mt-1 {{ \App\Support\Money::isPositive($balance) ? 'text-danger' : 'text-success' }}">
                        @if(\App\Support\Money::isPositive($balance)){{ __('Balance due') }}: <b class="font-monospace">{{ money($balance, false) }}</b>@else<i class="bi bi-check-circle-fill"></i> {{ __('Fully settled') }}@endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center"><i class="bi bi-list-ul me-1"></i> {{ __('Items') }} <span class="badge text-bg-secondary ms-2">{{ $si->items->count() }}</span></div>
            <div class="table-responsive"><table class="table table-erp mb-0 align-middle">
                <thead><tr><th style="width:2.5rem">#</th><th>{{ __('Item') }}</th><th>{{ __('Account') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end">{{ __('VAT') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                <tbody>
                @foreach($si->items as $n => $i)
                    <tr>
                        <td class="text-body-secondary">{{ $n + 1 }}</td>
                        <td style="white-space:normal;min-width:200px">
                            <div class="d-flex gap-2">
                                <span class="item-ico {{ $i->part ? 'part' : 'other' }}"><i class="bi {{ $i->part ? 'bi-gear' : 'bi-journal-text' }}"></i></span>
                                <div class="min-w-0"><div class="fw-semibold">{{ $i->description }}</div>
                                    @if($i->part)<div class="small text-body-secondary"><a href="{{ route('parts.show', $i->part) }}"><bdi dir="ltr" class="font-monospace">{{ $i->part->sku }}</bdi></a>@if($i->part->oem_number) · <bdi dir="ltr" class="font-monospace">{{ $i->part->oem_number }}</bdi>@endif</div>@endif</div>
                            </div>
                        </td>
                        <td class="small">{{ $i->account?->label() ?? ($i->part ? __('Inventory (via GRN)') : '—') }}</td>
                        <td class="text-end">{{ qty($i->qty) }}</td>
                        <td class="text-end font-monospace">{{ money($i->unit_cost, false) }}</td>
                        <td class="text-end font-monospace">{{ money($i->tax_amount, false) }}</td>
                        <td class="text-end font-monospace fw-semibold">{{ money($i->line_total, false) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @if($si->notes)<div class="card-footer small"><i class="bi bi-sticky me-1"></i>{{ $si->notes }}</div>@endif
        </div>
        @if($si->journal && auth()->user()->can('journal_entries.view'))
            <details class="card mb-3">
                <summary class="card-header d-flex align-items-center" style="cursor:pointer"><i class="bi bi-journal-text me-1"></i>{{ __('Accounting entry') }} <a class="ms-2" href="{{ route('journal.show', $si->journal) }}">{{ $si->journal->number }}</a></summary>
                <table class="table table-sm mb-0">@foreach($si->journal->lines as $l)<tr><td>{{ $l->account->label() }}</td><td class="text-end font-monospace">{{ (float) $l->debit ? money($l->debit, false) : '' }}</td><td class="text-end font-monospace">{{ (float) $l->credit ? money($l->credit, false) : '' }}</td></tr>@endforeach</table>
            </details>
        @endif
        <x-attachments :model="$si" :title="__('Supplier invoice scan & documents')"/>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-body doc-totals">
            <div><span>{{ __('Subtotal') }}</span><b>{{ money($si->subtotal, false) }}</b></div>
            @if(\App\Support\Money::isPositive($si->discount_total))<div class="text-danger"><span>{{ __('Discount') }}</span><b>-{{ money($si->discount_total, false) }}</b></div>@endif
            <div><span>{{ __('VAT') }}</span><b>{{ money($si->tax_total, false) }}</b></div>
            <div class="grand"><span>{{ __('Total') }}</span><b>{{ money($si->total) }}</b></div>
            @if($open)
                <div class="text-success"><span>{{ __('Paid') }}</span><b>{{ money($si->paid_amount, false) }}</b></div>
                <div class="due {{ \App\Support\Money::isPositive($balance) ? 'text-danger' : 'text-success' }}"><span>{{ __('Balance due') }}</span><b>{{ money($balance) }}</b></div>
            @endif
        </div></div>

        <div class="card mb-3"><div class="card-header"><i class="bi bi-diagram-3 me-1"></i>{{ __('Linked documents') }}</div><div class="card-body doc-meta">
            <div><span>{{ __('Purchase order') }}</span>@if($si->order)<a href="{{ route('purchase-orders.show', $si->order) }}">{{ $si->order->number }}</a>@else — @endif</div>
            <div><span>{{ __('Goods receipt (GRN)') }}</span>@if($si->receipt)<a href="{{ route('goods-receipts.show', $si->receipt) }}">{{ $si->receipt->number }}</a> · {{ money($si->receipt->total) }}@else — @endif</div>
            @if($si->creator)<div><span>{{ __('Created by') }}</span>{{ $si->creator->name }} · {{ $si->created_at?->format('Y-m-d H:i') }}</div>@endif
            @if($si->posted_at)<div><span>{{ __('Posted') }}</span>{{ $si->posted_at->format('Y-m-d H:i') }}@if($si->poster) · {{ $si->poster->name }}@endif</div>@endif
        </div></div>

        <div class="card mb-3"><div class="card-header"><i class="bi bi-cash-stack me-1"></i>{{ __('Payments') }}</div><ul class="list-group list-group-flush">
            @forelse($si->allocations as $a)<li class="list-group-item d-flex align-items-center small"><i class="bi bi-check-circle-fill text-success me-2"></i><div><a href="{{ route('payments.show', $a->payment) }}">{{ $a->payment->number }}</a><div class="text-body-secondary">{{ status_label($a->payment->method) }} · {{ $a->payment->date->format('Y-m-d') }}</div></div><span class="ms-auto font-monospace">{{ money($a->amount, false) }}</span></li>
            @empty<li class="list-group-item small text-body-secondary">{{ __('No payments yet.') }}</li>@endforelse
        </ul></div>
    </div>
</div>
@endsection
