@extends('layouts.portal')
@section('title', __('Warranties'))
@section('content')
<h5 class="mb-3">{{ __('My warranties') }}</h5>
@forelse($warranties as $w)
    <div class="card mb-2"><div class="card-body">
        <div class="d-flex"><b>{{ $w->description }}</b><span class="ms-auto">{!! status_badge($w->isValid() ? 'active' : 'expired') !!}</span></div>
        <div class="small text-body-secondary">{{ $w->vehicle?->plate() }} · {{ $w->start_date->format('Y-m-d') }} → {{ $w->end_date->format('Y-m-d') }} @if($w->km_limit)· {{ number_format($w->km_limit) }} km @endif</div>
        @foreach($w->claims as $c)<div class="small mt-1">{{ __('Claim') }} {{ $c->number }}: {!! status_badge($c->status) !!}</div>@endforeach
    </div></div>
@empty<div class="text-body-secondary">{{ __('No warranties.') }}</div>@endforelse
@endsection
