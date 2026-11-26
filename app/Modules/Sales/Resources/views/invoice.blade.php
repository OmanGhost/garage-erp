@extends('layouts.app')
@section('title', $inv->displayNumber())
@section('content')
@php
    $posted = $inv->isPosted();
    $credited = (string) $inv->creditNotes->where('status', 'posted')->sum('total');
    $balance = $posted ? $inv->balanceDue() : '0';
    $settled = \App\Support\Money::add($inv->paid_amount, $credited);
    $paidPct = (float) $inv->total > 0 ? min(100, round((float) $settled * 100 / (float) $inv->total)) : 0;
    $overdue = $posted && \App\Support\Money::isPositive($balance) && $inv->due_date && $inv->due_date->isPast() && ! $inv->due_date->isToday();
    $tone = ['draft' => 'secondary', 'held' => 'warning', 'posted' => 'primary', 'partially_paid' => 'warning', 'paid' => 'success', 'cancelled' => 'danger'][$inv->status] ?? 'secondary';
    $hasWarranties = $warranties->isNotEmpty();
    $canPrint = auth()->user()->can('invoices.print');
@endphp
<x-page-header :title="__('Invoice').' '.$inv->displayNumber()" icon="bi-receipt-cutoff" :breadcrumbs="[__('Invoices') => route('crud.index', 'invoices'), $inv->displayNumber()]">
    @if($canPrint)
    <div class="btn-group">
        <a href="{{ route('invoices.print', [$inv, 'a4']) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print') }}</a>
        <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('More print options') }}"></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">{{ __('Invoice') }}</h6></li>
            <li><a class="dropdown-item" href="{{ route('invoices.print', [$inv, 'a4']) }}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text me-2"></i>A4</a></li>
            <li><a class="dropdown-item" href="{{ route('invoices.print', [$inv, '80mm']) }}" target="_blank" rel="noopener"><i class="bi bi-receipt me-2"></i>{{ __('Receipt 80mm') }}</a></li>
            <li><a class="dropdown-item" href="{{ route('invoices.print', [$inv, 'pdf']) }}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>PDF</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ route('invoices.delivery-note', $inv) }}" target="_blank" rel="noopener"><i class="bi bi-truck me-2"></i>{{ __('Delivery note') }}</a></li>
            @if($inv->jobCard)@can('job_cards.print')<li><a class="dropdown-item" href="{{ route('job-cards.gate-pass', $inv->jobCard) }}" target="_blank" rel="noopener"><i class="bi bi-door-open me-2"></i>{{ __('Gate pass') }}</a></li>@endcan @endif
            @if($hasWarranties)<li><a class="dropdown-item" href="{{ route('invoices.warranties', $inv) }}" target="_blank" rel="noopener"><i class="bi bi-shield-check me-2 text-success"></i>{{ __('Warranty cards') }} ({{ $warranties->count() }})</a></li>@endif
        </ul>
    </div>
    @endif
    @if($hasWarranties && $canPrint)
        <a href="{{ route('invoices.warranties', $inv) }}" target="_blank" rel="noopener" class="btn btn-outline-success"><i class="bi bi-shield-check"></i> {{ __('Print warranty') }}</a>
    @endif
    @if(in_array($inv->status, ['draft', 'held']))
        @can('invoices.update')<a href="{{ route('invoices.edit', $inv) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>@endcan
        @can('invoices.post')<form method="post" action="{{ route('invoices.post', $inv) }}" data-confirm="{{ __('Post the invoice? Stock and accounts will be updated and it can no longer be edited.') }}">@csrf <x-idem/><button class="btn btn-success"><i class="bi bi-check2-circle"></i> {{ __('Post invoice') }}</button></form>@endcan
    @endif
    @if($posted)
        @if(\App\Support\Money::isPositive($balance))
            @can('payments.create')<a href="{{ route('payments.receive', ['customer_id' => $inv->customer_id, 'invoice_id' => $inv->id]) }}" class="btn btn-success"><i class="bi bi-wallet2"></i> {{ __('Receive payment') }}</a>@endcan
        @endif
        @can('credit_notes.create')<a href="{{ route('credit-notes.create', $inv) }}" class="btn btn-outline-danger"><i class="bi bi-arrow-return-left"></i> {{ __('Return / credit note') }}</a>@endcan
    @endif
</x-page-header>

{{-- summary --}}
<div class="card doc-hero mb-3 border-{{ $tone }}">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-lg-5">
                <div class="d-flex align-items-center gap-3">
                    <span class="doc-hero-icon bg-{{ $tone }}-subtle text-{{ $tone }}"><i class="bi bi-receipt-cutoff"></i></span>
                    <div class="min-w-0">
                        <div class="small text-body-secondary">{{ status_label($inv->type) }} · {{ status_label($inv->payment_terms) }}</div>
                        <div class="fs-4 fw-bold font-monospace text-truncate">{{ $inv->displayNumber() }}</div>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            {!! status_badge($inv->status) !!}
                            @if($overdue)<span class="badge text-bg-danger"><i class="bi bi-alarm"></i> {{ __('Overdue') }}</span>@endif
                            @if($hasWarranties)<span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle"><i class="bi bi-shield-check"></i> {{ trans_choice('{1} 1 warranty|[2,*] :n warranties', $warranties->count(), ['n' => $warranties->count()]) }}</span>@endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="doc-meta">
                    <div><span>{{ __('Customer') }}</span><a class="fw-semibold" href="{{ route('customers.show', $inv->customer) }}">{{ $inv->customer->name }}</a></div>
                    @if($inv->customer->phone)<div><span>{{ __('Mobile') }}</span><bdi dir="ltr">{{ $inv->customer->phone }}</bdi></div>@endif
                    @if($inv->jobCard)<div><span>{{ __('Job card') }}</span><a href="{{ route('job-cards.show', $inv->jobCard) }}">{{ $inv->jobCard->number }}</a> · {{ $inv->jobCard->vehicle?->plate() }}</div>@endif
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="doc-meta">
                    <div><span>{{ __('Date') }}</span>{{ $inv->issue_date->format('Y-m-d') }}</div>
                    <div><span>{{ __('Due') }}</span><span class="{{ $overdue ? 'text-danger fw-semibold' : '' }}">{{ $inv->due_date?->format('Y-m-d') ?? '—' }}</span></div>
                </div>
            </div>
            <div class="col-lg-2 text-lg-end">
                <div class="small text-body-secondary">{{ __('Total') }}</div>
                <div class="fs-3 fw-bold font-monospace lh-1">{{ money($inv->total) }}</div>
                @if($posted)
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
            <div class="card-header d-flex align-items-center"><i class="bi bi-list-ul me-1"></i> {{ __('Items') }} <span class="badge text-bg-secondary ms-2">{{ $inv->items->count() }}</span></div>
            <div class="table-responsive"><table class="table table-erp mb-0 align-middle doc-lines">
                <thead><tr><th style="width:2.5rem">#</th><th>{{ __('Item') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('VAT') }}</th><th class="text-end">{{ __('Total') }}</th>
                    @can('reports.view')<th class="text-end">{{ __('Cost') }}</th>@endcan</tr></thead>
                <tbody>
                @foreach($inv->items as $i => $it)
                    @php $w = $warranties->get($it->id); @endphp
                    <tr>
                        <td class="text-body-secondary">{{ $i + 1 }}</td>
                        <td style="white-space:normal;min-width:220px">
                            <div class="d-flex gap-2">
                                <span class="item-ico {{ $it->item_type }}"><i class="bi {{ ['part' => 'bi-gear', 'service' => 'bi-tools', 'labor' => 'bi-person-gear'][$it->item_type] ?? 'bi-dot' }}"></i></span>
                                <div class="min-w-0">
                                    <div class="fw-semibold">{{ $it->description }}</div>
                                    <div class="small text-body-secondary">
                                        {{ status_label($it->item_type) }}
                                        @if($it->part)· <bdi dir="ltr" class="font-monospace">{{ $it->part->sku }}</bdi>@if($it->part->oem_number) · <bdi dir="ltr" class="font-monospace">{{ $it->part->oem_number }}</bdi>@endif @endif
                                        @if((float) $it->returned_qty > 0)<span class="badge text-bg-danger">{{ __('Returned') }} {{ qty($it->returned_qty) }}</span>@endif
                                    </div>
                                    @if($w)
                                        <div class="warranty-chip mt-1">
                                            <i class="bi bi-shield-check"></i>
                                            <span>{{ __('Warranty until :d', ['d' => $w->end_date?->format('Y-m-d')]) }}@if($w->km_limit) · {{ number_format($w->km_limit) }} km @endif</span>
                                            @if($canPrint)<a href="{{ route('invoices.warranties', [$inv, 'a4', 'w' => $w->id]) }}" target="_blank" rel="noopener" class="ms-1"><i class="bi bi-printer"></i> {{ __('Print') }}</a>@endif
                                        </div>
                                    @elseif((int) $it->warranty_days > 0)
                                        <div class="small text-success mt-1"><i class="bi bi-shield"></i> {{ __('Warranty :n days — issued when the invoice is posted', ['n' => $it->warranty_days]) }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="text-end">{{ qty($it->qty) }}</td>
                        <td class="text-end font-monospace">{{ money($it->unit_price, false) }}</td>
                        <td class="text-end font-monospace {{ \App\Support\Money::isPositive($it->discount) ? 'text-danger' : 'text-body-secondary' }}">{{ money($it->discount, false) }}</td>
                        <td class="text-end font-monospace">{{ money($it->tax_amount, false) }}</td>
                        <td class="text-end font-monospace fw-semibold">{{ money($it->line_total, false) }}</td>
                        @can('reports.view')<td class="text-end font-monospace text-body-secondary">{{ money($it->cost_total, false) }}</td>@endcan
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @if($inv->notes)<div class="card-footer small"><i class="bi bi-sticky me-1"></i>{{ $inv->notes }}</div>@endif
        </div>

        @if($inv->journal && auth()->user()->can('journal_entries.view'))
            <details class="card mb-3">
                <summary class="card-header d-flex align-items-center" style="cursor:pointer"><i class="bi bi-journal-text me-1"></i>{{ __('Accounting entry') }} <a class="ms-2" href="{{ route('journal.show', $inv->journal) }}">{{ $inv->journal->number }}</a></summary>
                <table class="table table-sm mb-0">@foreach($inv->journal->lines as $l)<tr><td>{{ $l->account->label() }}</td><td class="text-end font-monospace">{{ (float) $l->debit ? money($l->debit, false) : '' }}</td><td class="text-end font-monospace">{{ (float) $l->credit ? money($l->credit, false) : '' }}</td></tr>@endforeach</table>
            </details>
        @endif
    </div>

    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-body doc-totals">
            <div><span>{{ __('Subtotal') }}</span><b>{{ money($inv->subtotal, false) }}</b></div>
            @if(\App\Support\Money::isPositive($inv->discount_total))<div class="text-danger"><span>{{ __('Discount') }}</span><b>-{{ money($inv->discount_total, false) }}</b></div>@endif
            <div><span>{{ __('VAT') }}</span><b>{{ money($inv->tax_total, false) }}</b></div>
            <div class="grand"><span>{{ __('Total') }}</span><b>{{ money($inv->total) }}</b></div>
            @if($posted)
                <div class="text-success"><span>{{ __('Paid') }}</span><b>{{ money($inv->paid_amount, false) }}</b></div>
                @if(\App\Support\Money::isPositive($credited))<div class="text-danger"><span>{{ __('Credit notes') }}</span><b>-{{ money($credited, false) }}</b></div>@endif
                <div class="due {{ \App\Support\Money::isPositive($balance) ? 'text-danger' : 'text-success' }}"><span>{{ __('Balance due') }}</span><b>{{ money($balance) }}</b></div>
            @endif
        </div></div>

        <div class="card mb-3"><div class="card-header"><i class="bi bi-info-circle me-1"></i>{{ __('Details') }}</div><div class="card-body doc-meta">
            <div><span>{{ __('Price level') }}</span>{{ status_label($inv->price_level) }}</div>
            <div><span>{{ __('Salesperson') }}</span>{{ $inv->salesperson?->name ?? '—' }}</div>
            @if($inv->creator)<div><span>{{ __('Created by') }}</span>{{ $inv->creator->name }} · {{ $inv->created_at?->format('Y-m-d H:i') }}</div>@endif
            @if($inv->posted_at)<div><span>{{ __('Posted') }}</span>{{ $inv->posted_at->format('Y-m-d H:i') }}</div>@endif
        </div></div>

        <div class="card mb-3"><div class="card-header"><i class="bi bi-wallet2 me-1"></i>{{ __('Payments') }}</div><ul class="list-group list-group-flush">
            @forelse($inv->allocations as $a)<li class="list-group-item d-flex align-items-center small"><i class="bi bi-check-circle-fill text-success me-2"></i><div><a href="{{ route('payments.show', $a->payment) }}">{{ $a->payment->number }}</a><div class="text-body-secondary">{{ status_label($a->payment->method) }} · {{ $a->payment->date->format('Y-m-d') }}</div></div><span class="ms-auto font-monospace">{{ money($a->amount, false) }}</span></li>
            @empty<li class="list-group-item small text-body-secondary">{{ __('No payments yet.') }}</li>@endforelse
        </ul></div>

        @if($hasWarranties)
            <div class="card mb-3 border-success-subtle"><div class="card-header d-flex align-items-center"><i class="bi bi-shield-check text-success me-1"></i>{{ __('Warranties') }}
                @if($canPrint)<a href="{{ route('invoices.warranties', $inv) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success ms-auto"><i class="bi bi-printer"></i> {{ __('Print all') }}</a>@endif</div>
                <ul class="list-group list-group-flush small">
                    @foreach($warranties as $w)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <div class="min-w-0"><div class="text-truncate">{{ $w->description }}</div><div class="text-body-secondary"><bdi dir="ltr">{{ $w->start_date?->format('Y-m-d') }} → {{ $w->end_date?->format('Y-m-d') }}</bdi></div></div>
                            <span class="ms-auto">{!! status_badge($w->status) !!}</span>
                            @if($canPrint)<a href="{{ route('invoices.warranties', [$inv, 'a4', 'w' => $w->id]) }}" target="_blank" rel="noopener" title="{{ __('Print warranty') }}" aria-label="{{ __('Print warranty') }}"><i class="bi bi-printer"></i></a>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($inv->creditNotes->isNotEmpty())
            <div class="card"><div class="card-header"><i class="bi bi-arrow-return-left me-1"></i>{{ __('Credit notes') }}</div><ul class="list-group list-group-flush">
                @foreach($inv->creditNotes as $cn)<li class="list-group-item d-flex align-items-center gap-2 small"><a href="{{ route('credit-notes.show', $cn) }}">{{ $cn->displayNumber() }}</a><span class="ms-auto font-monospace">{{ money($cn->total, false) }}</span>{!! status_badge($cn->status) !!}@can('credit_notes.print')<a href="{{ route('credit-notes.print', $cn) }}" target="_blank" rel="noopener" title="{{ __('Print') }}" aria-label="{{ __('Print') }}"><i class="bi bi-printer"></i></a>@endcan</li>@endforeach
            </ul></div>
        @endif
    </div>
</div>
@endsection
