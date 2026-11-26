@extends('layouts.app')
@section('title', __('Cross reference'))
@section('content')
<x-page-header :title="__('Cross reference')" icon="bi-arrow-left-right" :breadcrumbs="[__('Spare Parts & Inventory'), __('Cross reference')]"/>
<div class="card mb-3"><div class="card-body">
    <form class="row g-2 align-items-center">
        <div class="col-md-9"><div class="input-group input-group-lg"><span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
            <input name="q" value="{{ $q }}" class="form-control font-monospace" dir="ltr" autofocus data-scan-target placeholder="{{ __('OEM / factory number, alternative number or SKU') }}" aria-label="{{ __('Part number') }}"></div></div>
        <div class="col-md-3 d-grid"><button class="btn btn-primary btn-lg"><i class="bi bi-search"></i> {{ __('Find interchangeable parts') }}</button></div>
        <div class="col-12 form-text">{{ __('Finds every part sharing this number: genuine, aftermarket brands, superseded numbers and used / remanufactured equivalents, with their stock. Spaces and dashes are ignored.') }}</div>
    </form>
</div></div>

@if($q !== '')
    @if($parts->isEmpty())
        <div class="empty-state"><i class="bi bi-search"></i>{{ __('No part found for «:q».', ['q' => $q]) }}</div>
    @else
        @if($numbers->isNotEmpty())
            <div class="mb-3">@foreach($numbers as $n)<span class="badge text-bg-light border me-1 mb-1 fs-6"><bdi dir="ltr" class="font-monospace">{{ $n->number }}</bdi>@if($n->brand) <span class="small text-body-secondary">{{ $n->brand }}</span>@endif</span>@endforeach</div>
        @endif
        <div class="row g-3">
            @foreach($parts as $g)
                @php $avail = (float) $g->on_hand - (float) $g->reserved; @endphp
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('parts.show', $g) }}" class="card h-100 text-decoration-none text-body {{ $avail > 0 ? 'border-success' : '' }}">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-2">
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-semibold">{{ $g->displayName() }}</div>
                                    <div class="small text-body-secondary"><bdi dir="ltr" class="font-monospace">{{ $g->sku }}</bdi>@if($g->oem_number) · OEM <bdi dir="ltr" class="font-monospace">{{ $g->oem_number }}</bdi>@endif</div>
                                    <div class="small">{{ $g->brand?->name }}</div>
                                </div>
                                <span class="badge {{ in_array($g->part_type, ['genuine', 'oem']) ? 'text-bg-primary' : (in_array($g->part_type, ['used', 'refurbished', 'damaged']) ? 'text-bg-warning' : 'text-bg-info') }}">{{ status_label($g->part_type) }}</span>
                            </div>
                            <div class="d-flex mt-2 align-items-end">
                                <div><div class="small text-body-secondary">{{ __('Available') }}</div><div class="fs-5 fw-bold {{ $avail > 0 ? 'text-success' : 'text-danger' }}">{{ qty($avail) }}</div></div>
                                <div class="ms-auto text-end"><div class="small text-body-secondary">{{ __('Retail price') }}</div><div class="fs-5 fw-bold font-monospace">{{ money($g->sale_price, false) }}</div></div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
@endif
@endsection
