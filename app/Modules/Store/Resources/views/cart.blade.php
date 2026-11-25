@extends('layouts.store')
@section('title', __('Cart'))
@section('content')
<h4 class="mb-3">{{ __('Shopping cart') }}</h4>
@if(! $lines)
    <div class="empty-state"><i class="bi bi-cart"></i>{{ __('Your cart is empty.') }} <a href="{{ route('store.home', $company) }}">{{ __('Continue shopping') }}</a></div>
@else
<form method="post" action="{{ route('store.cart.update', $company) }}" class="card">@csrf
    <table class="table mb-0 align-middle">
        <thead class="table-light"><tr><th>{{ __('Part') }}</th><th style="width:110px">{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
        <tbody>@foreach($lines as $l)<tr><td>{{ $l['part']->displayName() }} @if((float) $l['qty'] > (float) $l['available'])<div class="small text-danger">{{ __('Only :n available', ['n' => qty($l['available'])]) }}</div>@endif</td>
            <td><input type="number" name="qty[{{ $l['part']->id }}]" value="{{ $l['qty'] }}" min="0" max="99" class="form-control form-control-sm"></td>
            <td class="text-end font-monospace">{{ money($l['unit_price'], false) }}</td><td class="text-end font-monospace">{{ money((float) $l['net'] + (float) $l['tax'], false) }}</td></tr>@endforeach</tbody>
        <tfoot><tr class="fw-bold"><td colspan="3" class="text-end">{{ __('Total incl. VAT') }}</td><td class="text-end font-monospace">{{ money(array_sum(array_map(fn($l) => (float) $l['net'] + (float) $l['tax'], $lines))) }}</td></tr></tfoot>
    </table>
    <div class="card-footer d-flex gap-2"><button class="btn btn-outline-secondary">{{ __('Update cart') }}</button><a href="{{ route('store.checkout', $company) }}" class="btn btn-primary ms-auto">{{ __('Checkout') }} <i class="bi bi-arrow-{{ is_rtl() ? 'left' : 'right' }}"></i></a></div>
</form>
@endif
@endsection
