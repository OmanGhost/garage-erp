@extends('layouts.public')
@section('title', __('Track your vehicle'))
@section('content')
<div class="card mx-auto" style="max-width:720px">
    <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="plate-badge"><span class="flag">{{ $jc->vehicle->plate_country }}</span>{{ $jc->vehicle->plate() }}</span>
            <span>{{ $jc->vehicle->title() }}</span>
            <span class="ms-auto">{!! status_badge($jc->status) !!}</span>
        </div>
        <div class="small text-body-secondary mb-3">{{ __('Job card') }} {{ $jc->number }} · {{ __('Received') }} {{ $ci->received_at->format('Y-m-d H:i') }} @if($jc->promised_at)· {{ __('Expected') }} {{ $jc->promised_at->format('Y-m-d H:i') }}@endif</div>
        <div class="progress mb-3" style="height:10px"><div class="progress-bar bg-success" style="width:{{ $jc->progress }}%"></div></div>
        @foreach($jc->quotations as $q)
            @if($q->status === 'sent')
                <a href="{{ route('public.quotation', $q->access_token) }}" class="alert alert-warning d-block text-decoration-none"><i class="bi bi-file-earmark-text"></i> {{ __('A quotation is waiting for your approval') }}: {{ $q->number }} — {{ money($q->total) }}</a>
            @endif
        @endforeach
        <h6>{{ __('Progress') }}</h6>
        <ul class="timeline">
            @foreach($jc->histories->sortBy('id') as $h)
                <li><b>{{ status_label($h->to_status) }}</b><div class="small text-body-secondary">{{ $h->created_at?->format('Y-m-d H:i') }}</div></li>
            @endforeach
            @foreach($jc->notes as $n)
                <li><div>{{ $n->note }}</div><div class="small text-body-secondary">{{ $n->created_at->format('Y-m-d H:i') }}</div></li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
