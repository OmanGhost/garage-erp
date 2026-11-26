@extends('layouts.print')
@section('title', __('Warranty card').' #'.$w->id)
@section('pdf-url', route('warranties.print', [$w, 'pdf']))
@section('styles')
    .card-frame { border: 3px double #1f6feb; border-radius: 10px; padding: 10px 12px; }
    .big { font-size: 15px; font-weight: bold; }
    .stamp { text-align: center; font-weight: bold; padding: 4px; border-radius: 6px; margin: 6px 0; }
    .stamp.ok { border: 2px solid #198754; color: #198754; }
    .stamp.no { border: 2px solid #b02a37; color: #b02a37; }
    .signs td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 8px; }
@endsection
@section('content')
@php
    $valid = $w->status === 'active' && $w->end_date && $w->end_date->endOfDay()->isFuture();
    $ref = 'WR-'.str_pad((string) $w->id, 6, '0', STR_PAD_LEFT);
@endphp
<div class="card-frame">
    <div class="doc-title" style="margin-top:0">بطاقة ضمان <span class="bilingual">Warranty Card</span></div>
    @if($valid)<div class="stamp ok">ضمان ساري حتى {{ $w->end_date->format('Y-m-d') }} / VALID UNTIL {{ $w->end_date->format('Y-m-d') }}</div>
    @else<div class="stamp no">{{ status_label($w->status) }} / {{ strtoupper($w->status) }}</div>@endif
    <table class="head"><tr>
        <td>
            <table class="grid">
                <tr><th>رقم الضمان / Warranty No.</th><td class="big">{{ $ref }}</td></tr>
                <tr><th>البند المضمون / Covered item</th><td><b>{{ $w->description }}</b>@if($w->part)<br><span dir="ltr">{{ $w->part->sku }}</span> {{ $w->part->brand?->name }}@endif</td></tr>
                @if($w->serial_number)<tr><th>الرقم التسلسلي / Serial</th><td dir="ltr">{{ $w->serial_number }}</td></tr>@endif
                <tr><th>العميل / Customer</th><td>{{ $w->customer?->name }} · <span dir="ltr">{{ $w->customer?->phone }}</span></td></tr>
                <tr><th>المركبة / Vehicle</th><td>{{ $w->vehicle?->label() ?? '—' }}</td></tr>
                <tr><th>الفاتورة / Invoice</th><td>{{ $w->invoice?->displayNumber() ?? '—' }} @if($w->invoice) · {{ $w->invoice->issue_date?->format('Y-m-d') }}@endif</td></tr>
                <tr><th>مدة الضمان / Period</th><td class="big"><span dir="ltr">{{ $w->start_date?->format('Y-m-d') }} → {{ $w->end_date?->format('Y-m-d') }}</span></td></tr>
                <tr><th>حد المسافة / Km limit</th><td>{{ $w->km_limit ? number_format($w->km_limit).' km' : 'غير محدد / Unlimited' }}@if($w->start_odometer) · {{ __('from') }} {{ number_format($w->start_odometer) }} km @endif</td></tr>
            </table>
        </td>
        <td style="width:28%;text-align:center;vertical-align:top">
            <img src="{{ \App\Support\Codes::qr(route('crud.show', ['warranties', $w->id]), 3) }}" style="width:110px">
            <div class="muted">امسح للتحقق<br>Scan to verify</div>
        </td>
    </tr></table>
    <div class="box" style="margin-top:8px">
        <b>شروط الضمان / Warranty terms</b><br>
        {!! nl2br(e($w->terms ?: setting('warranty.terms') ?: 'يغطي الضمان عيوب التصنيع والتركيب ضمن المدة أو المسافة المحددة، أيهما أسبق. لا يشمل الضمان سوء الاستخدام أو الحوادث أو الإصلاح لدى جهة أخرى. يجب إحضار هذه البطاقة والفاتورة عند المطالبة.')) !!}
        @unless($w->terms)<br><span class="muted">The warranty covers manufacturing and fitting defects within the period or distance above, whichever comes first. It does not cover misuse, accidents or repairs elsewhere. Bring this card and the invoice with any claim.</span>@endunless
    </div>
    @if($w->claims->isNotEmpty())
        <p class="muted" style="margin-top:6px">المطالبات السابقة / Previous claims: @foreach($w->claims as $cl){{ $cl->number }} ({{ status_label($cl->status) }})@if(! $loop->last), @endif @endforeach</p>
    @endif
    <table class="signs" style="margin-top:18px"><tr>
        <td><div class="sign"></div>ختم وتوقيع الورشة / Workshop stamp & signature</td>
        <td><div class="sign"></div>توقيع العميل / Customer signature</td>
    </tr></table>
</div>
@endsection
