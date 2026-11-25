@extends('layouts.app')
@section('title', $q->number)
@section('content')
<x-page-header :title="__('Quotation').' '.$q->number.' v'.$q->version" icon="bi-file-earmark-text" :breadcrumbs="[__('Quotations') => route('crud.index', 'quotations'), $q->number]"
    :subtitle="e($q->customer->name).' '.($q->vehicle ? '· '.e($q->vehicle->label()) : '').' · '.status_badge($q->status).($q->is_supplementary ? ' <span class=\'badge text-bg-warning\'>'.__('Additional work').'</span>' : '')">
    <a href="{{ route('quotations.print', $q) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print') }}</a>
    <a href="{{ route('quotations.print', [$q, 'format' => 'pdf']) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-file-pdf"></i> PDF</a>
    @if($q->isEditable())
        @can('quotations.update')<a href="{{ route('quotations.edit', $q) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>@endcan
        @can('quotations.update')<form method="post" action="{{ route('quotations.send', $q) }}">@csrf <x-idem/><button class="btn btn-primary"><i class="bi bi-send"></i> {{ __('Send to customer') }}</button></form>@endcan
    @endif
    @if(!in_array($q->status, ['converted']))
        @can('quotations.create')<form method="post" action="{{ route('quotations.revise', $q) }}">@csrf <x-idem/><button class="btn btn-outline-secondary"><i class="bi bi-files"></i> {{ __('New version') }}</button></form>@endcan
    @endif
    @if(! $q->job_card_id && in_array($q->status, ['draft', 'sent', 'approved', 'partially_approved']))
        @can('invoices.create')<form method="post" action="{{ route('quotations.convert', $q) }}">@csrf <x-idem/><button class="btn btn-success"><i class="bi bi-receipt"></i> {{ __('Convert to invoice') }}</button></form>@endcan
    @endif
</x-page-header>

<div class="row g-3">
    <div class="col-xl-8">
        <form method="post" action="{{ route('quotations.respond', $q) }}" class="card">@csrf <x-idem/>
            <div class="table-responsive"><table class="table table-erp mb-0 align-middle">
                <thead><tr><th>#</th><th>{{ __('Item') }}</th><th>{{ __('Option') }}</th><th>{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('VAT') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Decision') }}</th></tr></thead>
                <tbody>
                @foreach($q->items as $i => $it)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><div>{{ $it->description }}</div><div class="small text-body-secondary">{{ status_label($it->item_type) }} @if($it->part_condition && $it->part_condition !== 'new')· <span class="badge text-bg-info">{{ status_label($it->part_condition) }}</span>@endif</div></td>
                        <td>{{ $it->option_group }}</td>
                        <td>{{ qty($it->qty) }}</td>
                        <td class="text-end font-monospace">{{ money($it->unit_price, false) }}</td>
                        <td class="text-end font-monospace">{{ money($it->discount, false) }}</td>
                        <td class="text-end font-monospace">{{ money($it->tax_amount, false) }}</td>
                        <td class="text-end font-monospace">{{ money((float) $it->line_total + (float) $it->tax_amount, false) }}</td>
                        <td>
                            @if($q->isEditable() && auth()->user()->can('quotations.approve'))
                                <select name="decisions[{{ $it->id }}]" class="form-select form-select-sm"><option value="approved">{{ __('Approve') }}</option><option value="rejected">{{ __('Reject') }}</option></select>
                            @else {!! status_badge($it->approval_status) !!} @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="7" class="text-end">{{ __('Subtotal') }}</td><td class="text-end font-monospace">{{ money($q->subtotal, false) }}</td><td></td></tr>
                    <tr><td colspan="7" class="text-end">{{ __('Discount') }}</td><td class="text-end font-monospace">-{{ money($q->discount_total, false) }}</td><td></td></tr>
                    <tr><td colspan="7" class="text-end">{{ __('VAT') }}</td><td class="text-end font-monospace">{{ money($q->tax_total, false) }}</td><td></td></tr>
                    <tr class="fw-bold"><td colspan="7" class="text-end">{{ __('Total') }}</td><td class="text-end font-monospace">{{ money($q->total) }}</td><td></td></tr>
                    @if((float) $q->approved_total > 0)<tr class="table-success fw-bold"><td colspan="7" class="text-end">{{ __('Approved total') }}</td><td class="text-end font-monospace">{{ money($q->approved_total) }}</td><td></td></tr>@endif
                </tfoot>
            </table></div>
            @if($q->isEditable() && auth()->user()->can('quotations.approve'))
            <div class="card-body border-top row g-2 align-items-end">
                <div class="col-md-4"><label class="form-label small">{{ __('Customer / representative name') }}</label><input name="response_by" class="form-control form-control-sm" value="{{ $q->customer->name }}" required></div>
                <div class="col-md-3"><label class="form-label small">{{ __('Channel') }}</label><select name="channel" class="form-select form-select-sm"><option value="in_person">{{ __('In person') }}</option><option value="phone">{{ __('Phone') }}</option><option value="whatsapp">WhatsApp</option><option value="email">{{ __('Email') }}</option></select></div>
                <div class="col-md-3"><label class="form-label small">{{ __('Notes') }}</label><input name="notes" class="form-control form-control-sm"></div>
                <div class="col-md-2"><button class="btn btn-sm btn-success w-100"><i class="bi bi-check2-square"></i> {{ __('Record response') }}</button></div>
            </div>
            @endif
        </form>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-body small">
            <div><b>{{ __('Valid until') }}:</b> {{ $q->valid_until?->format('Y-m-d') }} @if($q->isExpired())<span class="badge text-bg-danger">{{ __('Expired') }}</span>@endif</div>
            <div><b>{{ __('Created by') }}:</b> {{ $q->creator?->name }} · {{ $q->created_at->format('Y-m-d H:i') }}</div>
            @if($q->sent_at)<div><b>{{ __('Sent') }}:</b> {{ $q->sent_at->format('Y-m-d H:i') }}</div>@endif
            @if($q->responded_at)<div><b>{{ __('Response') }}:</b> {{ $q->response_by }} ({{ status_label($q->response_channel) }}) · {{ $q->responded_at->format('Y-m-d H:i') }}</div>@endif
            @if($q->response_notes)<div class="text-body-secondary">{{ $q->response_notes }}</div>@endif
            @if($q->jobCard)<div class="mt-2"><a href="{{ route('job-cards.show', $q->jobCard) }}"><i class="bi bi-clipboard-check"></i> {{ $q->jobCard->number }}</a></div>@endif
            <div class="mt-2"><label class="form-label small mb-1">{{ __('Customer approval link') }}</label><input class="form-control form-control-sm" readonly value="{{ route('public.quotation', $q->access_token) }}" onclick="this.select()"></div>
        </div></div>
        <div class="card"><div class="card-header">{{ __('Versions') }}</div><div class="list-group list-group-flush">
            @foreach($versions as $v)
                <a href="{{ route('quotations.show', $v) }}" class="list-group-item list-group-item-action d-flex {{ $v->id === $q->id ? 'active' : '' }}"><span>v{{ $v->version }}</span><span class="ms-auto me-2 font-monospace">{{ money($v->total, false) }}</span>{!! status_badge($v->status) !!}</a>
            @endforeach
        </div></div>
    </div>
</div>
@endsection
