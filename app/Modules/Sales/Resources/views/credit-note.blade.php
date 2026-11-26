@extends('layouts.app')
@section('title', __('Credit note').' '.$cn->displayNumber())
@section('content')
@php
    $posted = $cn->status === 'posted';
    $inv = $cn->invoice;
    $postedNotes = $inv->creditNotes->where('status', 'posted');
    $credited = $cn->creditedToAccount();
@endphp
<x-page-header :title="__('Credit note').' '.$cn->displayNumber()" icon="bi-file-earmark-minus"
    :breadcrumbs="[__('Credit Notes') => route('crud.index', 'credit-notes'), $cn->displayNumber()]"
    :subtitle="__('Invoice').' <a href=\''.route('invoices.show', $inv).'\'>'.e($inv->displayNumber()).'</a> · '.e($cn->customer->name).' · '.status_badge($cn->status)">
    <div class="d-flex flex-wrap gap-2">
        @can('credit_notes.print')
            <a class="btn btn-outline-secondary" href="{{ route('credit-notes.print', $cn) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Print') }}</a>
            <a class="btn btn-outline-secondary" href="{{ route('credit-notes.print', [$cn, 'pdf']) }}" target="_blank" rel="noopener"><i class="bi bi-filetype-pdf"></i> PDF</a>
        @endcan
        @if(! $posted)
            @can('credit_notes.approve')
            <form method="post" action="{{ route('credit-notes.post', $cn) }}" class="d-flex gap-2" data-confirm="{{ __('Post this credit note?') }}" data-confirm-type="primary">@csrf <x-idem/>
                <select name="refund_bank_account_id" class="form-select"><option value="">{{ __('No refund') }}</option>@foreach($banks as $id => $n)<option value="{{ $id }}">{{ __('Refund from') }} {{ $n }}</option>@endforeach</select>
                <button class="btn btn-danger text-nowrap"><i class="bi bi-check2-circle"></i> {{ __('Post') }}</button>
            </form>
            @endcan
        @endif
    </div>
</x-page-header>

@unless($posted)
    <div class="alert alert-warning d-flex gap-2 align-items-center"><i class="bi bi-hourglass-split fs-5"></i>
        <div>{{ __('This credit note is a draft: it has no effect on the invoice, the customer balance, stock or VAT until it is posted.') }}</div></div>
@endunless

{{-- summary --}}
<div class="row g-3 mb-3">
    @foreach([
        [__('Credit total'), money($cn->total), 'bi-file-earmark-minus', 'danger'],
        [__('Refunded to customer'), money($cn->refunded_amount ?? 0), 'bi-cash-coin', 'success'],
        [__('Credited to customer account'), money($posted ? $credited : 0), 'bi-person-check', 'primary'],
        [__('Invoice balance due'), money($inv->balanceDue()), 'bi-receipt', 'secondary'],
    ] as [$l, $v, $icon, $c])
        <div class="col-6 col-lg-3"><div class="card kpi h-100"><div class="card-body d-flex align-items-center gap-3">
            <span class="kpi-icon bg-{{ $c }}-subtle text-{{ $c }}"><i class="bi {{ $icon }}"></i></span>
            <div><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-5">{{ $v }}</div></div>
        </div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-box-seam"></i> {{ __('Returned items') }}</div>
            <div class="table-responsive"><table class="table table-erp mb-0">
                <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('Price charged') }}</th><th class="text-end">{{ __('VAT') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                <tbody>@foreach($cn->items as $i)<tr>
                    <td>{{ $i->description }}@if($i->part)<div class="small text-body-secondary font-monospace" dir="ltr">{{ $i->part->sku }}</div>@endif</td>
                    <td>{{ qty($i->qty) }}</td><td class="text-end font-monospace">{{ money($i->unit_price, false) }}</td><td class="text-end font-monospace">{{ money($i->discount, false) }}</td>
                    <td class="text-end font-monospace">{{ money((float) $i->qty > 0 ? \App\Support\Money::div($i->line_total, $i->qty, 3) : 0, false) }}</td>
                    <td class="text-end font-monospace">{{ money($i->tax_amount, false) }}</td><td class="text-end font-monospace">{{ money($i->line_total, false) }}</td></tr>@endforeach</tbody>
                <tfoot>
                    <tr><td colspan="6" class="text-end">{{ __('Taxable amount') }}</td><td class="text-end font-monospace">{{ money($cn->subtotal) }}</td></tr>
                    <tr><td colspan="6" class="text-end">{{ __('VAT') }}</td><td class="text-end font-monospace">{{ money($cn->tax_total) }}</td></tr>
                    <tr class="fw-bold"><td colspan="6" class="text-end">{{ __('Total') }}</td><td class="text-end font-monospace">{{ money($cn->total) }}</td></tr>
                </tfoot>
            </table></div>
            <div class="card-body small border-top">
                <div><b>{{ __('Reason') }}:</b> {{ $cn->reason ?: '—' }}</div>
                <div><b>{{ __('Restock') }}:</b> {{ $cn->restock ? __('Yes') : __('No') }} @if($cn->restock && $cn->warehouse)— {{ $cn->warehouse->displayName() }}@endif</div>
            </div>
        </div>

        @if($cn->journal)
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center"><span><i class="bi bi-journal-text"></i> {{ __('Journal entry') }}</span>
                    <a class="ms-auto small" href="{{ route('journal.show', $cn->journal) }}">{{ $cn->journal->number ?? '#'.$cn->journal->id }}</a></div>
                <div class="table-responsive"><table class="table table-sm mb-0 small">
                    <thead><tr><th>{{ __('Account') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
                    @foreach($cn->journal->lines as $l)<tr><td>{{ $l->account?->label() }}</td><td class="text-end font-monospace">{{ (float) $l->debit ? money($l->debit, false) : '' }}</td><td class="text-end font-monospace">{{ (float) $l->credit ? money($l->credit, false) : '' }}</td></tr>@endforeach
                </table></div>
            </div>
        @endif
    </div>

    <div class="col-xl-4">
        {{-- tracking timeline --}}
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clock-history"></i> {{ __('Tracking') }}</div>
            <ul class="list-group list-group-flush small cn-timeline">
                <li class="list-group-item d-flex gap-2"><i class="bi bi-plus-circle-fill text-secondary"></i>
                    <div><div class="fw-semibold">{{ __('Created') }}</div><div class="text-body-secondary">{{ $cn->created_at?->format('Y-m-d H:i') }} · {{ $cn->creator?->name ?? '—' }}</div></div></li>
                <li class="list-group-item d-flex gap-2"><i class="bi {{ $posted ? 'bi-check-circle-fill text-success' : 'bi-circle text-body-tertiary' }}"></i>
                    <div><div class="fw-semibold">{{ __('Posted') }}</div><div class="text-body-secondary">{{ $posted ? $cn->posted_at?->format('Y-m-d H:i').' · '.($cn->poster?->name ?? '—') : __('Waiting for approval') }}</div></div></li>
                @if($cn->restock)
                    <li class="list-group-item d-flex gap-2"><i class="bi {{ $posted ? 'bi-box-arrow-in-down text-success' : 'bi-circle text-body-tertiary' }}"></i>
                        <div><div class="fw-semibold">{{ __('Returned to stock') }}</div><div class="text-body-secondary">{{ $cn->warehouse?->displayName() ?? __('Original warehouse') }}</div></div></li>
                @endif
                <li class="list-group-item d-flex gap-2">
                    @if($cn->refundPayment)
                        <i class="bi bi-cash-coin text-success"></i>
                        <div><div class="fw-semibold">{{ __('Refunded to customer') }}</div>
                            <div class="text-body-secondary"><a href="{{ route('payments.show', $cn->refundPayment) }}">{{ $cn->refundPayment->number }}</a> · {{ money($cn->refundPayment->amount) }} · {{ $cn->refundPayment->bankAccount?->name }}</div>
                            @can('payments.print')<a class="small" href="{{ route('vouchers.payment', $cn->refundPayment) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Print voucher') }}</a>@endcan
                        </div>
                    @elseif($posted && (float) $cn->refunded_amount > 0)
                        <i class="bi bi-cash-coin text-success"></i><div><div class="fw-semibold">{{ __('Refunded to customer') }}</div><div class="text-body-secondary">{{ money($cn->refunded_amount) }}</div></div>
                    @else
                        <i class="bi {{ $posted ? 'bi-person-check-fill text-primary' : 'bi-circle text-body-tertiary' }}"></i>
                        <div><div class="fw-semibold">{{ __('Credited to customer account') }}</div><div class="text-body-secondary">{{ $posted ? __('No cash refund: the amount reduces what the customer owes.') : '—' }}</div></div>
                    @endif
                </li>
            </ul>
        </div>

        {{-- original invoice --}}
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-receipt"></i> {{ __('Original invoice') }}</div>
            <div class="card-body small">
                <div class="d-flex mb-1"><span>{{ __('Invoice') }}</span><a class="ms-auto" href="{{ route('invoices.show', $inv) }}">{{ $inv->displayNumber() }}</a></div>
                <div class="d-flex mb-1"><span>{{ __('Date') }}</span><span class="ms-auto">{{ $inv->issue_date->format('Y-m-d') }}</span></div>
                <div class="d-flex mb-1"><span>{{ __('Invoice total') }}</span><span class="ms-auto font-monospace">{{ money($inv->total) }}</span></div>
                <div class="d-flex mb-1 text-danger"><span>{{ __('Total returned (posted)') }}</span><span class="ms-auto font-monospace">-{{ money($postedNotes->sum('total')) }}</span></div>
                <div class="d-flex mb-1"><span>{{ __('Paid') }}</span><span class="ms-auto font-monospace">{{ money($inv->paid_amount) }}</span></div>
                <div class="d-flex fw-semibold border-top pt-1"><span>{{ __('Balance due') }}</span><span class="ms-auto font-monospace">{{ money($inv->balanceDue()) }}</span></div>
            </div>
            @if($others->isNotEmpty())
                <div class="card-header border-top small">{{ __('Other credit notes for this invoice') }}</div>
                <ul class="list-group list-group-flush small">
                    @foreach($others as $o)<li class="list-group-item d-flex"><a href="{{ route('credit-notes.show', $o) }}">{{ $o->displayNumber() }}</a><span class="ms-auto font-monospace me-2">{{ money($o->total, false) }}</span>{!! status_badge($o->status) !!}</li>@endforeach
                </ul>
            @endif
        </div>

        {{-- customer --}}
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person"></i> {{ __('Customer') }}</div>
            <div class="card-body small">
                <a href="{{ route('customers.show', $cn->customer) }}" class="fw-semibold">{{ $cn->customer->name }}</a>
                <div class="text-body-secondary"><bdi dir="ltr">{{ $cn->customer->phone }}</bdi></div>
                <div class="d-flex mt-2"><span>{{ __('Current balance') }}</span><span class="ms-auto font-monospace">{{ money($cn->customer->balance()) }}</span></div>
            </div>
        </div>
    </div>
</div>
@endsection
