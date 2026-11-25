@extends('layouts.store')
@section('title', $o->number)
@section('content')
<div class="card mx-auto" style="max-width:640px"><div class="card-body text-center">
    <i class="bi bi-bag-check text-success" style="font-size:3rem"></i>
    <h4>{{ __('Order') }} {{ $o->number }}</h4>
    <div class="mb-3">{!! status_badge($o->status) !!} {!! status_badge($o->payment_status) !!}</div>
    <table class="table text-start">
        @foreach($o->items as $i)<tr><td>{{ $i->description }} × {{ qty($i->qty) }}</td><td class="text-end font-monospace">{{ money((float) $i->line_total + (float) $i->tax_amount, false) }}</td></tr>@endforeach
        @if((float) $o->discount_total)<tr><td>{{ __('Discount') }}</td><td class="text-end font-monospace">-{{ money($o->discount_total, false) }}</td></tr>@endif
        @if((float) $o->shipping_fee)<tr><td>{{ __('Delivery') }}</td><td class="text-end font-monospace">{{ money($o->shipping_fee, false) }}</td></tr>@endif
        <tr class="fw-bold"><td>{{ __('Total') }}</td><td class="text-end font-monospace">{{ money($o->total) }}</td></tr>
    </table>
    <p class="small text-body-secondary">{{ __('We will contact you on :p to confirm your order.', ['p' => $o->customer_phone]) }}</p>
    <a href="{{ route('store.home', $company) }}" class="btn btn-outline-primary">{{ __('Continue shopping') }}</a>
</div></div>
@endsection
