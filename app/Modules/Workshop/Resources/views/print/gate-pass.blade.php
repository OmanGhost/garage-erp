@extends('layouts.print')
@section('title', preg_replace('/^[A-Z]+-/', 'GP-', $jc->number))
@section('pdf-url', route('job-cards.gate-pass', [$jc, 'pdf']))
@section('styles')
    .stamp { text-align: center; font-weight: bold; font-size: 14px; padding: 6px; border-radius: 6px; margin: 6px 0; }
    .stamp.ok { border: 2px solid #198754; color: #198754; }
    .stamp.no { border: 2px solid #b02a37; color: #b02a37; }
    .plate { font-size: 20px; font-weight: bold; letter-spacing: 2px; }
    .signs td { width: 25%; text-align: center; vertical-align: bottom; padding: 0 5px; font-size: 10px; }
    .signs .sign { height: 46px; }
@endsection
@section('content')
@php
    $v = $jc->vehicle;
    $paid = ! \App\Support\Money::isPositive($balance);
    $exitAt = $exit?->logged_at ?? $jc->delivered_at;
    $number = preg_match('/^[A-Z]+-/', $jc->number) ? preg_replace('/^[A-Z]+-/', 'GP-', $jc->number) : 'GP-'.$jc->number;
@endphp
<div class="doc-title">تصريح خروج مركبة <span class="bilingual">Vehicle Gate Pass</span></div>

@if($released)
    <div class="stamp ok">مسموح بالخروج — تم تسليم المركبة / RELEASED — vehicle delivered</div>
@else
    <div class="stamp no">غير مسموح بالخروج — لم يتم التسليم بعد / NOT RELEASED — vehicle not delivered yet</div>
@endif
@unless($paid)
    <div class="stamp no">يوجد مبلغ مستحق / Balance due: {{ money($balance) }}</div>
@endunless

<table class="head"><tr>
    <td>
        <table class="grid">
            <tr><th>رقم التصريح / Pass No.</th><td><b>{{ $number }}</b></td></tr>
            <tr><th>أمر العمل / Job card</th><td>{{ $jc->number }}</td></tr>
            <tr><th>وقت الخروج / Exit time</th><td>{{ $exitAt?->format('Y-m-d H:i') ?? '—' }}</td></tr>
            <tr><th>رقم اللوحة / Plate</th><td><span class="plate" dir="ltr">{{ $v->plate() }}</span> <span class="muted">{{ $v->plate_country }}</span></td></tr>
            <tr><th>المركبة / Vehicle</th><td>{{ $v->title() }}@if($v->color) · {{ $v->color }}@endif</td></tr>
            <tr><th>رقم الهيكل / VIN</th><td dir="ltr">{{ $v->vin ?: '—' }}</td></tr>
            <tr><th>العداد / Odometer</th><td>{{ number_format((int) ($jc->odometer_out ?? $jc->odometer_in)) }} km</td></tr>
        </table>
    </td>
    <td style="width:34%;text-align:center;vertical-align:top">
        <img src="{{ \App\Support\Codes::qr(route('job-cards.show', $jc), 3) }}" style="width:110px">
        <div class="muted">امسح للتحقق<br>Scan to verify</div>
    </td>
</tr></table>

<table class="grid" style="margin-top:6px">
    <tr><th>العميل / Customer</th><td>{{ $jc->customer->name }} · <span dir="ltr">{{ $jc->customer->phone }}</span></td></tr>
    <tr><th>الفواتير / Invoices</th><td>
        @forelse($invoices as $inv){{ $inv->displayNumber() }} ({{ money($inv->total) }})@if(! $loop->last), @endif @empty — @endforelse
    </td></tr>
    <tr><th>حالة الدفع / Payment</th><td><b>{{ $paid ? 'مدفوع بالكامل / Fully paid' : 'غير مدفوع بالكامل / Not fully paid' }}</b></td></tr>
    @if($jc->delivered_to_name)
        <tr><th>المستلم / Collected by</th><td><b>{{ $jc->delivered_to_name }}</b>
            @if($jc->delivered_to_id_number) · <span dir="ltr">{{ $jc->delivered_to_id_number }}</span>@endif
            — {{ $jc->delivered_to_type === 'authorized' ? 'شخص مفوَّض / Authorized person'.($jc->authorization ? ' ('.$jc->authorization->number.')' : '') : 'المالك / Owner' }}</td></tr>
    @endif
    <tr><th>سلّمها / Released by</th><td>{{ $exit?->user?->name ?? $jc->advisor?->name ?? '—' }}</td></tr>
</table>

<p class="muted" style="margin-top:6px">
    يُسمح بخروج المركبة الموضحة أعلاه من الورشة. يُسلّم هذا التصريح لحارس البوابة عند الخروج.<br>
    The vehicle above is authorised to leave the workshop. Hand this pass to the gate guard on exit.
</p>

<table class="signs" style="margin-top:14px"><tr>
    <td><div class="sign"></div>مستشار الخدمة<br>Service advisor</td>
    <td><div class="sign"></div>الصندوق<br>Cashier</td>
    <td><div class="sign"></div>حارس البوابة<br>Gate guard</td>
    <td><div class="sign"></div>العميل (استلمت المركبة)<br>Customer (received)</td>
</tr></table>
@endsection
