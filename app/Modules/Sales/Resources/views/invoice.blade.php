@extends('layouts.app')
@section('title', $inv->displayNumber())
@section('content')
<x-page-header :title="__('Invoice').' '.$inv->displayNumber()" icon="bi-receipt-cutoff" :breadcrumbs="[__('Invoices') => route('crud.index', 'invoices'), $inv->displayNumber()]"
    :subtitle="e($inv->customer->name).' · '.status_label($inv->type).' · '.status_badge($inv->status)">
    <div class="btn-group">
        <a href="{{ route('invoices.print', [$inv, 'a4']) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> A4</a>
        <a href="{{ route('invoices.print', [$inv, '80mm']) }}" target="_blank" class="btn btn-outline-secondary">80mm</a>
        <a href="{{ route('invoices.print', [$inv, 'pdf']) }}" target="_blank" class="btn btn-outline-secondary">PDF</a>
    </div>
    @if(in_array($inv->status, ['draft', 'held']))
        @can('invoices.update')<a href="{{ route('invoices.edit', $inv) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>@endcan
        @can('invoices.post')<form method="post" action="{{ route('invoices.post', $inv) }}" data-confirm="{{ __('Post the invoice? Stock and accounts will be updated and it can no longer be edited.') }}">@csrf <x-idem/><button class="btn btn-success"><i class="bi bi-check2-circle"></i> {{ __('Post invoice') }}</button></form>@endcan
    @endif
    @if($inv->isPosted())
        @if(\App\Support\Money::isPositive($inv->balanceDue()))
            @can('payments.create')<a href="{{ route('payments.receive', ['customer_id' => $inv->customer_id, 'invoice_id' => $inv->id]) }}" class="btn btn-success"><i class="bi bi-wallet2"></i> {{ __('Receive payment') }}</a>@endcan
        @endif
        @can('credit_notes.create')<a href="{{ route('credit-notes.create', $inv) }}" class="btn btn-outline-danger"><i class="bi bi-arrow-return-left"></i> {{ __('Return / credit note') }}</a>@endcan
    @endif
</x-page-header>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3"><div class="table-responsive"><table class="table table-erp mb-0 align-middle">
            <thead><tr><th>#</th><th>{{ __('Description') }}</th><th>{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('VAT') }}</th><th class="text-end">{{ __('Total') }}</th>
                @can('reports.view')<th class="text-end">{{ __('Cost') }}</th>@endcan</tr></thead>
            <tbody>
            @foreach($inv->items as $i => $it)
                <tr><td>{{ $i + 1 }}</td><td>{{ $it->description }}<div class="small text-body-secondary">{{ status_label($it->item_type) }} @if($it->part)· {{ $it->part->sku }}@endif @if((float) $it->returned_qty > 0)<span class="badge text-bg-danger">{{ __('Returned') }} {{ qty($it->returned_qty) }}</span>@endif</div></td>
                    <td>{{ qty($it->qty) }}</td><td class="text-end font-monospace">{{ money($it->unit_price, false) }}</td><td class="text-end font-monospace">{{ money($it->discount, false) }}</td>
                    <td class="text-end font-monospace">{{ money($it->tax_amount, false) }}</td><td class="text-end font-monospace">{{ money($it->line_total, false) }}</td>
                    @can('reports.view')<td class="text-end font-monospace text-body-secondary">{{ money($it->cost_total, false) }}</td>@endcan</tr>
            @endforeach
            </tbody>
        </table></div></div>
        @if($inv->journal && auth()->user()->can('journal_entries.view'))
            <div class="card mb-3"><div class="card-header"><i class="bi bi-journal-text me-1"></i>{{ __('Accounting entry') }} <a href="{{ route('journal.show', $inv->journal) }}">{{ $inv->journal->number }}</a></div>
                <table class="table table-sm mb-0">@foreach($inv->journal->lines as $l)<tr><td>{{ $l->account->label() }}</td><td class="text-end font-monospace">{{ (float) $l->debit ? money($l->debit, false) : '' }}</td><td class="text-end font-monospace">{{ (float) $l->credit ? money($l->credit, false) : '' }}</td></tr>@endforeach</table></div>
        @endif
    </div>
    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex"><span>{{ __('Subtotal') }}</span><span class="ms-auto font-monospace">{{ money($inv->subtotal) }}</span></div>
            <div class="d-flex text-danger"><span>{{ __('Discount') }}</span><span class="ms-auto font-monospace">-{{ money($inv->discount_total) }}</span></div>
            <div class="d-flex"><span>{{ __('VAT') }}</span><span class="ms-auto font-monospace">{{ money($inv->tax_total) }}</span></div>
            <div class="d-flex fs-5 fw-bold border-top mt-2 pt-2"><span>{{ __('Total') }}</span><span class="ms-auto font-monospace">{{ money($inv->total) }}</span></div>
            <div class="d-flex text-success"><span>{{ __('Paid') }}</span><span class="ms-auto font-monospace">{{ money($inv->paid_amount) }}</span></div>
            @if($inv->creditNotes->where('status', 'posted')->isNotEmpty())<div class="d-flex text-danger"><span>{{ __('Credit notes') }}</span><span class="ms-auto font-monospace">-{{ money($inv->creditNotes->where('status', 'posted')->sum('total')) }}</span></div>@endif
            @if($inv->isPosted())<div class="d-flex fw-bold"><span>{{ __('Balance due') }}</span><span class="ms-auto font-monospace">{{ money($inv->balanceDue()) }}</span></div>@endif
        </div></div>
        <div class="card mb-3"><div class="card-body small">
            <div><b>{{ __('Customer') }}:</b> <a href="{{ route('customers.show', $inv->customer) }}">{{ $inv->customer->name }}</a></div>
            <div><b>{{ __('Date') }}:</b> {{ $inv->issue_date->format('Y-m-d') }} · <b>{{ __('Due') }}:</b> {{ $inv->due_date?->format('Y-m-d') ?? '—' }}</div>
            <div><b>{{ __('Terms') }}:</b> {{ status_label($inv->payment_terms) }} · {{ status_label($inv->price_level) }}</div>
            @if($inv->jobCard)<div><b>{{ __('Job card') }}:</b> <a href="{{ route('job-cards.show', $inv->jobCard) }}">{{ $inv->jobCard->number }}</a> · {{ $inv->jobCard->vehicle?->plate() }}</div>@endif
            <div><b>{{ __('Salesperson') }}:</b> {{ $inv->salesperson?->name }}</div>
            @if($inv->posted_at)<div><b>{{ __('Posted') }}:</b> {{ $inv->posted_at->format('Y-m-d H:i') }}</div>@endif
            @if($inv->notes)<div class="mt-1 text-body-secondary">{{ $inv->notes }}</div>@endif
        </div></div>
        <div class="card mb-3"><div class="card-header">{{ __('Payments') }}</div><ul class="list-group list-group-flush">
            @forelse($inv->allocations as $a)<li class="list-group-item d-flex small"><a href="{{ route('payments.show', $a->payment) }}">{{ $a->payment->number }}</a><span class="ms-2 text-body-secondary">{{ status_label($a->payment->method) }} · {{ $a->payment->date->format('Y-m-d') }}</span><span class="ms-auto font-monospace">{{ money($a->amount, false) }}</span></li>
            @empty<li class="list-group-item small text-body-secondary">{{ __('No payments yet.') }}</li>@endforelse
        </ul></div>
        @if($inv->creditNotes->isNotEmpty())
            <div class="card"><div class="card-header">{{ __('Credit notes') }}</div><ul class="list-group list-group-flush">
                @foreach($inv->creditNotes as $cn)<li class="list-group-item d-flex align-items-center gap-2 small"><a href="{{ route('credit-notes.show', $cn) }}">{{ $cn->displayNumber() }}</a><span class="ms-auto font-monospace">{{ money($cn->total, false) }}</span>{!! status_badge($cn->status) !!}@can('credit_notes.print')<a href="{{ route('credit-notes.print', $cn) }}" target="_blank" rel="noopener" title="{{ __('Print') }}" aria-label="{{ __('Print') }}"><i class="bi bi-printer"></i></a>@endcan</li>@endforeach
            </ul></div>
        @endif
    </div>
</div>
@endsection
