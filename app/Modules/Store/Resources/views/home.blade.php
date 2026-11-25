@extends('layouts.store')
@section('title', __('Spare parts store'))
@section('content')
<form class="card mb-4"><div class="card-body row g-2">
    <div class="col-md-4"><input name="q" value="{{ $f['q'] ?? '' }}" class="form-control" placeholder="{{ __('Search part name, number or OEM...') }}"></div>
    <div class="col-md-2"><select name="make_id" class="form-select"><option value="">{{ __('Vehicle make') }}</option>@foreach($makes as $id => $n)<option value="{{ $id }}" @selected(($f['make_id'] ?? '') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="col-md-1"><input name="year" type="number" value="{{ $f['year'] ?? '' }}" class="form-control" placeholder="{{ __('Year') }}"></div>
    <div class="col-md-2"><select name="part_category_id" class="form-select"><option value="">{{ __('Category') }}</option>@foreach($categories as $id => $n)<option value="{{ $id }}" @selected(($f['part_category_id'] ?? '') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="col-md-2"><select name="part_type" class="form-select"><option value="">{{ __('New & used') }}</option><option value="genuine" @selected(($f['part_type'] ?? '') === 'genuine')>{{ __('Genuine') }}</option><option value="aftermarket" @selected(($f['part_type'] ?? '') === 'aftermarket')>{{ __('Aftermarket') }}</option><option value="used" @selected(($f['part_type'] ?? '') === 'used')>{{ __('Used') }}</option></select></div>
    <div class="col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
</div></form>
<div class="row g-3">
    @forelse($parts as $p)
        <div class="col-6 col-md-4 col-lg-3"><div class="card h-100 product-card">
            <a href="{{ route('store.part', [$company, $p->id]) }}">@if($p->imageUrl())<img src="{{ $p->imageUrl() }}" class="card-img-top" alt="">@else<div class="d-flex align-items-center justify-content-center bg-white" style="height:150px"><i class="bi bi-gear-wide-connected text-body-tertiary" style="font-size:4rem"></i></div>@endif</a>
            <div class="card-body d-flex flex-column">
                <div class="small text-body-secondary">{{ $p->brand?->name }} @if($p->oem_number)· {{ $p->oem_number }}@endif</div>
                <a href="{{ route('store.part', [$company, $p->id]) }}" class="fw-semibold text-decoration-none text-body">{{ $p->displayName() }}</a>
                @if(in_array($p->part_type, ['used', 'refurbished']))<span class="badge text-bg-warning align-self-start">{{ status_label($p->part_type) }} {{ $p->condition_grade }}</span>@endif
                <div class="mt-auto d-flex align-items-center pt-2">
                    <span class="fw-bold text-brand">{{ money((float) $p->sale_price * (1 + (float) $p->tax_rate / 100)) }}</span>
                    @if((float) $p->on_hand - (float) $p->reserved > 0)
                        <form method="post" action="{{ route('store.cart.add', $company) }}" class="ms-auto">@csrf<input type="hidden" name="part_id" value="{{ $p->id }}"><button class="btn btn-sm btn-primary"><i class="bi bi-cart-plus"></i></button></form>
                    @else<span class="ms-auto badge text-bg-secondary">{{ __('Out of stock') }}</span>@endif
                </div>
            </div>
        </div></div>
    @empty
        <div class="col-12"><div class="empty-state"><i class="bi bi-search"></i>{{ __('No parts found.') }}</div></div>
    @endforelse
</div>
<div class="mt-3">{{ $parts->links() }}</div>
@endsection
