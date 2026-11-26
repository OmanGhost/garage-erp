@extends('layouts.print')
@section('title', __('Key tag').' '.$jc->vehicle?->plate())
@section('styles')
    .tag { border: 2px dashed #333; border-radius: 10px; padding: 6px; text-align: center; }
    .plate { font-size: 26px; font-weight: bold; letter-spacing: 2px; font-family: monospace; }
    .code { font-size: 16px; font-weight: bold; font-family: monospace; }
@endsection
@section('content')
<div class="tag">
    <div class="muted">بطاقة مفتاح / Key tag</div>
    <div class="plate">{{ $jc->vehicle?->plate() }}</div>
    <div>{{ $jc->vehicle?->title() }}</div>
    <div class="muted">{{ $jc->number }} · {{ $jc->received_at?->format('Y-m-d') }}</div>
    <img src="{{ \App\Support\Codes::qr(route('tracking.card', [$jc, 'via' => 'qr']), 4) }}" style="width:130px;margin:4px auto;display:block" alt="QR">
    <img src="{{ \App\Support\Codes::barcode($jc->key_tag, 2, 36) }}" style="max-width:100%;height:36px" alt="{{ $jc->key_tag }}">
    <div class="code">{{ $jc->key_tag }}</div>
    @if($jc->key_hook)<div>خطاف / Hook: <b>{{ $jc->key_hook }}</b></div>@endif
</div>
@endsection
