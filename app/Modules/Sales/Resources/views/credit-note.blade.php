@extends('layouts.app')
@section('title', __('Credit note'))
@section('content')
<x-page-header :title="__('Credit note').' '.($cn->number ?? 'DRAFT-'.$cn->id)" icon="bi-file-earmark-minus" :breadcrumbs="[__('Credit Notes') => route('crud.index', 'credit-notes'), $cn->number ?? '#'.$cn->id]"
    :subtitle="__('Invoice').' <a href=\''.route('invoices.show', $cn->invoice).'\'>'.e($cn->invoice->number).'</a> · '.e($cn->customer->name).' · '.status_badge($cn->status)">
    @if($cn->status === 'draft')
        @can('credit_notes.approve')
        <form method="post" action="{{ route('credit-notes.post', $cn) }}" class="d-flex gap-2" data-confirm="{{ __('Post this credit note?') }}">@csrf <x-idem/>
            <select name="refund_bank_account_id" class="form-select"><option value="">{{ __('No refund') }}</option>@foreach($banks as $id => $n)<option value="{{ $id }}">{{ __('Refund from') }} {{ $n }}</option>@endforeach</select>
            <button class="btn btn-danger text-nowrap">{{ __('Post') }}</button>
        </form>
        @endcan
    @endif
</x-page-header>
<div class="card"><div class="table-responsive"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('Price charged') }}</th><th class="text-end">{{ __('VAT') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
    <tbody>@foreach($cn->items as $i)<tr><td>{{ $i->description }}</td><td>{{ qty($i->qty) }}</td><td class="text-end font-monospace">{{ money($i->unit_price, false) }}</td><td class="text-end font-monospace">{{ money($i->discount, false) }}</td><td class="text-end font-monospace">{{ money((float) $i->qty > 0 ? \App\Support\Money::div($i->line_total, $i->qty, 3) : 0, false) }}</td><td class="text-end font-monospace">{{ money($i->tax_amount, false) }}</td><td class="text-end font-monospace">{{ money($i->line_total, false) }}</td></tr>@endforeach</tbody>
    <tfoot><tr class="fw-bold"><td colspan="6" class="text-end">{{ __('Total') }}</td><td class="text-end font-monospace">{{ money($cn->total) }}</td></tr>
        <tr><td colspan="6" class="text-end">{{ __('Refunded') }}</td><td class="text-end font-monospace">{{ money($cn->refunded_amount) }}</td></tr></tfoot>
</table></div>
<div class="card-body small">{{ __('Reason') }}: {{ $cn->reason }} · {{ __('Restock') }}: {{ $cn->restock ? __('Yes') : __('No') }} {{ $cn->warehouse?->displayName() }}</div></div>
@endsection
