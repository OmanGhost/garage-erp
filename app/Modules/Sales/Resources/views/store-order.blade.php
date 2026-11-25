@extends('layouts.app')
@section('title', $o->number)
@section('content')
<x-page-header :title="__('Online order').' '.$o->number" icon="bi-globe2" :breadcrumbs="[__('Online Orders') => route('crud.index', 'store-orders'), $o->number]" :subtitle="status_badge($o->status).' '.status_badge($o->payment_status)">
    @can('store_orders.update')
        @unless($o->invoice_id)<form method="post" action="{{ route('store-orders.action', [$o, 'invoice']) }}">@csrf <x-idem/><button class="btn btn-primary"><i class="bi bi-receipt"></i> {{ __('Confirm & invoice') }}</button></form>@endunless
        @if($o->payment_status !== 'paid')<form method="post" action="{{ route('store-orders.action', [$o, 'paid']) }}" class="d-flex gap-1">@csrf <x-idem/><select name="bank_account_id" class="form-select">@foreach($banks as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select><button class="btn btn-success text-nowrap">{{ __('Mark paid') }}</button></form>@endif
        @foreach(['processing', 'shipped', 'delivered', 'cancelled'] as $s)
            @if($o->status !== $s)<form method="post" action="{{ route('store-orders.action', [$o, $s]) }}">@csrf <x-idem/><button class="btn btn-outline-secondary">{{ status_label($s) }}</button></form>@endif
        @endforeach
    @endcan
</x-page-header>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><table class="table table-erp mb-0">
        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
        <tbody>@foreach($o->items as $i)<tr><td>{{ $i->description }} <small class="text-body-secondary">{{ $i->part?->sku }}</small></td><td>{{ qty($i->qty) }}</td><td class="text-end font-monospace">{{ money($i->unit_price, false) }}</td><td class="text-end font-monospace">{{ money($i->line_total, false) }}</td></tr>@endforeach</tbody>
        <tfoot>
            <tr><td colspan="3" class="text-end">{{ __('Discount') }} @if($o->coupon)({{ $o->coupon->code }})@endif</td><td class="text-end font-monospace">-{{ money($o->discount_total, false) }}</td></tr>
            <tr><td colspan="3" class="text-end">{{ __('VAT') }}</td><td class="text-end font-monospace">{{ money($o->tax_total, false) }}</td></tr>
            <tr><td colspan="3" class="text-end">{{ __('Shipping') }}</td><td class="text-end font-monospace">{{ money($o->shipping_fee, false) }}</td></tr>
            <tr class="fw-bold"><td colspan="3" class="text-end">{{ __('Total') }}</td><td class="text-end font-monospace">{{ money($o->total) }}</td></tr>
        </tfoot>
    </table></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-body small">
        <div><b>{{ $o->customer_name }}</b></div><div dir="ltr">{{ $o->customer_phone }}</div><div>{{ $o->customer_email }}</div>
        <div class="mt-2"><b>{{ __('Shipping') }}:</b> {{ status_label($o->shipping_method) }}</div><div>{{ $o->shipping_address }}</div>
        <div class="mt-2"><b>{{ __('Payment') }}:</b> {{ status_label($o->payment_method) }} {{ $o->payment_reference }}</div>
        @if($o->invoice)<div class="mt-2"><a href="{{ route('invoices.show', $o->invoice) }}">{{ $o->invoice->number }}</a></div>@endif
        @if($o->notes)<div class="mt-2 text-body-secondary">{{ $o->notes }}</div>@endif
    </div></div></div>
</div>
@endsection
