@extends('layouts.store')
@section('title', __('Checkout'))
@section('content')
<h4 class="mb-3">{{ __('Checkout') }}</h4>
<form method="post" action="{{ route('store.order.place', $company) }}" class="row g-3" x-data="{ ship: 'pickup' }">@csrf
    <div class="col-lg-7"><div class="card"><div class="card-body row g-2">
        <div class="col-md-6"><label class="form-label required">{{ __('Name') }}</label><input name="customer_name" class="form-control" value="{{ old('customer_name', $user?->name) }}" required></div>
        <div class="col-md-6"><label class="form-label required">{{ __('Mobile') }}</label><input name="customer_phone" class="form-control" dir="ltr" placeholder="+968 9xxxxxxx" value="{{ old('customer_phone', $user?->phone) }}" required></div>
        <div class="col-md-6"><label class="form-label">{{ __('Email') }}</label><input type="email" name="customer_email" class="form-control" value="{{ old('customer_email', $user?->email) }}"></div>
        <div class="col-md-6"><label class="form-label">{{ __('Coupon') }}</label><input name="coupon" class="form-control text-uppercase" value="{{ old('coupon') }}"></div>
        <div class="col-12"><label class="form-label">{{ __('Delivery') }}</label>
            <div class="d-flex gap-3"><div class="form-check"><input class="form-check-input" type="radio" name="shipping_method" value="pickup" x-model="ship" id="sp"><label class="form-check-label" for="sp">{{ __('Pick up from store') }}</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="shipping_method" value="delivery" x-model="ship" id="sd"><label class="form-check-label" for="sd">{{ __('Home delivery') }} ({{ money(setting('store.delivery_fee', '2.000')) }})</label></div></div></div>
        <div class="col-12" x-show="ship === 'delivery'"><label class="form-label">{{ __('Address') }}</label><textarea name="shipping_address" class="form-control" rows="2"></textarea></div>
        <div class="col-12"><label class="form-label">{{ __('Payment') }}</label>
            <div class="d-flex gap-3"><div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="cod" id="pc" checked><label class="form-check-label" for="pc">{{ __('Pay on pickup / delivery') }}</label></div>
            @if($gateway)<div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="online" id="po"><label class="form-check-label" for="po">{{ __('Pay online (card)') }}</label></div>@endif</div></div>
        <div class="col-12"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
    </div></div></div>
    <div class="col-lg-5"><div class="card"><div class="card-header">{{ __('Order summary') }}</div><ul class="list-group list-group-flush">
        @foreach($lines as $l)<li class="list-group-item d-flex small">{{ $l['part']->displayName() }} × {{ $l['qty'] }}<span class="ms-auto font-monospace">{{ money((float) $l['net'] + (float) $l['tax'], false) }}</span></li>@endforeach
        <li class="list-group-item d-flex fw-bold">{{ __('Total incl. VAT') }}<span class="ms-auto font-monospace">{{ money(array_sum(array_map(fn($l) => (float) $l['net'] + (float) $l['tax'], $lines))) }}</span></li>
    </ul><div class="card-body"><button class="btn btn-success btn-lg w-100">{{ __('Place order') }}</button></div></div></div>
</form>
@endsection
