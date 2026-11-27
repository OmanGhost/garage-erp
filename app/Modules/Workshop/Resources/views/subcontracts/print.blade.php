@extends('layouts.print')
@section('title', __('Outside work order').' '.$o->number)
@section('content')
<div class="doc-title">أمر عمل خارجي <span class="bilingual">Outside work order</span></div>
<table class="grid">
    <tr><th style="width:38%">رقم الأمر / Order No.</th><td><b>{{ $o->number }}</b> · {{ status_label($o->status) }}</td></tr>
    <tr><th>الورشة الخارجية / Subcontractor</th><td>{{ $o->supplier?->name }} @if($o->supplier?->phone)<span dir="ltr">· {{ $o->supplier->phone }}</span>@endif</td></tr>
    <tr><th>أمر العمل / Job card</th><td>{{ $o->jobCard?->number }}</td></tr>
    <tr><th>المركبة / Vehicle</th><td><b>{{ $o->jobCard?->vehicle?->plate() }}</b> — {{ $o->jobCard?->vehicle?->title() }} @if($o->jobCard?->vehicle?->vin)<span dir="ltr">· {{ $o->jobCard->vehicle->vin }}</span>@endif</td></tr>
    <tr><th>نوع العمل / Work type</th><td>{{ __(ucfirst($o->work_type)) }}</td></tr>
    <tr><th>المطلوب / Work required</th><td>{{ $o->description }}</td></tr>
    <tr><th>القطعة المرسلة / Item sent</th><td>{{ $o->item_sent ?: '—' }}</td></tr>
    <tr><th>التكلفة المتفق عليها / Agreed cost</th><td>{{ money($o->quoted_cost) }}</td></tr>
    <tr><th>تاريخ الإرسال / Sent</th><td>{{ $o->sent_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
    <tr><th>الإرجاع المتوقع / Expected back</th><td>{{ $o->expected_at?->format('Y-m-d') ?? '—' }}</td></tr>
    <tr><th>الضمان / Warranty</th><td>{{ $o->warranty_days ? $o->warranty_days.' يوم / days' : '—' }}@if($o->warranty_until) · {{ $o->warranty_until->format('Y-m-d') }}@endif</td></tr>
    @if($o->notes)<tr><th>ملاحظات / Notes</th><td>{!! nl2br(e($o->notes)) !!}</td></tr>@endif
</table>
<p class="muted" style="margin-top:8px">القطعة المذكورة ملك للعميل وتُعاد إلى الورشة بعد إنجاز العمل. / The item above is the customer's property and must be returned to the workshop when the work is done.</p>
<table class="grid" style="margin-top:24px"><tr>
    <td style="height:60px;width:50%;vertical-align:bottom;text-align:center">توقيع المُرسِل / Sent by</td>
    <td style="height:60px;vertical-align:bottom;text-align:center">توقيع المستلم / Received by</td>
</tr></table>
@endsection
