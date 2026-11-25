@extends('layouts.store')
@section('title', $p->displayName())
@section('content')
<div class="row g-4">
    <div class="col-md-5"><div class="card"><div class="card-body text-center">@if($p->imageUrl())<img src="{{ $p->imageUrl() }}" class="img-fluid" alt="">@else<i class="bi bi-gear-wide-connected text-body-tertiary" style="font-size:8rem"></i>@endif</div></div></div>
    <div class="col-md-7">
        <h3>{{ $p->displayName() }}</h3>
        <div class="text-body-secondary mb-2">{{ $p->brand?->name }} · {{ __('Part no.') }} {{ $p->oem_number ?? $p->sku }} · {{ status_label($p->part_type) }} {{ $p->condition_grade }}</div>
        <div class="fs-3 fw-bold text-brand mb-1">{{ money((float) $p->sale_price * (1 + (float) $p->tax_rate / 100)) }}</div>
        <div class="small text-body-secondary mb-3">{{ __('Price includes VAT') }} {{ qty($p->tax_rate) }}% · @if((float) $available > 0)<span class="text-success">{{ __('In stock') }}</span>@else<span class="text-danger">{{ __('Out of stock') }}</span>@endif · {{ __('Warranty') }} {{ $p->warranty_days }} {{ __('days') }}</div>
        @if((float) $available > 0)
        <form method="post" action="{{ route('store.cart.add', $company) }}" class="d-flex gap-2 mb-3" style="max-width:300px">@csrf<input type="hidden" name="part_id" value="{{ $p->id }}">
            <input type="number" name="qty" value="1" min="1" max="{{ min(99, (int) $available) }}" class="form-control"><button class="btn btn-primary text-nowrap"><i class="bi bi-cart-plus"></i> {{ __('Add to cart') }}</button></form>
        @endif
        @if($p->description)<p>{{ $p->description }}</p>@endif
        @if($p->compatibilities->isNotEmpty())<h6>{{ __('Fits') }}</h6><ul>@foreach($p->compatibilities as $c)<li>{{ $c->make?->name }} {{ $c->vehicleModel?->name }} {{ $c->year_from }}{{ $c->year_to ? '–'.$c->year_to : '' }}</li>@endforeach</ul>@endif
    </div>
</div>
@endsection
