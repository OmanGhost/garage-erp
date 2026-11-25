@extends('layouts.portal')
@section('title', __('Invoices'))
@section('content')
<div class="d-flex mb-3"><h5 class="mb-0">{{ __('My invoices') }}</h5><a href="{{ route('portal.statement') }}" class="ms-auto btn btn-sm btn-outline-primary">{{ __('Statement') }}</a></div>
@foreach($invoices as $i)
    <div class="card mb-2"><div class="card-body d-flex align-items-center gap-2">
        <div><div class="fw-semibold">{{ $i->number }}</div><div class="small text-body-secondary">{{ $i->issue_date->format('Y-m-d') }} · {!! status_badge($i->status) !!}</div></div>
        <div class="ms-auto text-end"><div class="font-monospace fw-bold">{{ money($i->total) }}</div>@if(\App\Support\Money::isPositive($i->balanceDue()))<div class="small text-danger">{{ __('Due') }} {{ money($i->balanceDue()) }}</div>@endif</div>
        <a href="{{ route('portal.invoices.pdf', $i) }}" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-file-pdf"></i></a>
        @if($gateway && \App\Support\Money::isPositive($i->balanceDue()))<form method="post" action="{{ route('portal.invoices.pay', $i) }}">@csrf<button class="btn btn-sm btn-success">{{ __('Pay online') }}</button></form>@endif
    </div></div>
@endforeach
{{ $invoices->links() }}
@endsection
