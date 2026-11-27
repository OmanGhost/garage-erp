@extends('layouts.print')
@section('title', __('Gift card').' '.$card->code)
@section('styles')
    .gcard { border: 3px dashed #c026d3; border-radius: 14px; padding: 16px; text-align: center; background: #fdf4ff; }
    .gamount { font-size: 30px; font-weight: bold; color: #a21caf; }
    .gcode { font-size: 20px; font-family: monospace; letter-spacing: 2px; font-weight: bold; }
@endsection
@section('content')
<div class="gcard">
    <div style="font-size:18px;font-weight:bold">بطاقة هدية / Gift card</div>
    @if($card->recipient_name)<div style="margin-top:6px">إلى / To: <b>{{ $card->recipient_name }}</b></div>@endif
    <div class="gamount">{{ money($card->initial_amount) }}</div>
    <img src="{{ \App\Support\Codes::barcode($card->code, 2, 40) }}" style="max-width:100%;height:40px" alt="{{ $card->code }}">
    <div class="gcode">{{ $card->code }}</div>
    <div class="muted" style="margin-top:6px">صالحة حتى / Valid until {{ $card->expires_at?->format('Y-m-d') }} · الرصيد / Balance {{ money($card->balance) }}</div>
    <div class="muted">تُستخدم كوسيلة دفع على فواتيرنا / Accepted as payment on our invoices</div>
</div>
@endsection
