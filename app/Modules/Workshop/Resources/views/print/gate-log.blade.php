@extends('layouts.print')
@section('title', $g->reference())
@section('pdf-url', route('gate-logs.print', [$g, 'pdf']))
@section('styles')
    .dir { text-align: center; font-weight: bold; font-size: 15px; padding: 6px; border-radius: 6px; margin: 6px 0; }
    .dir.in { border: 2px solid #0d6efd; color: #0d6efd; }
    .dir.out { border: 2px solid #198754; color: #198754; }
    .plate { font-size: 20px; font-weight: bold; letter-spacing: 2px; }
    .signs td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 8px; }
@endsection
@section('content')
@php $v = $g->vehicle; $jc = $g->jobCard; @endphp
<div class="doc-title">سجل حركة مركبة <span class="bilingual">Vehicle Gate Movement</span></div>
<div class="dir {{ $g->direction }}">
    {{ $g->direction === 'in' ? 'دخول إلى الورشة / ENTRY' : 'خروج من الورشة / EXIT' }}
</div>
<table class="head"><tr>
    <td>
        <table class="grid">
            <tr><th>رقم الحركة / Reference</th><td><b>{{ $g->reference() }}</b></td></tr>
            <tr><th>الوقت / Time</th><td>{{ $g->logged_at?->format('Y-m-d H:i') }}</td></tr>
            <tr><th>رقم اللوحة / Plate</th><td><span class="plate" dir="ltr">{{ $v?->plate() }}</span> <span class="muted">{{ $v?->plate_country }}</span></td></tr>
            <tr><th>المركبة / Vehicle</th><td>{{ $v?->title() }}@if($v?->color) · {{ $v->color }}@endif</td></tr>
            <tr><th>العميل / Customer</th><td>{{ $jc?->customer?->name ?? $v?->customer?->name }}</td></tr>
            <tr><th>أمر العمل / Job card</th><td>{{ $jc?->number ?? '—' }}</td></tr>
            <tr><th>العداد / Odometer</th><td>{{ $g->odometer ? number_format($g->odometer).' km' : '—' }}</td></tr>
            <tr><th>{{ $g->direction === 'in' ? 'سلّمها / Brought by' : 'استلمها / Collected by' }}</th><td>{{ $g->person_name ?: ($jc?->customer?->name ?? '—') }}@if($g->person_id_number) · {{ $g->person_id_number }}@endif</td></tr>
            <tr><th>الموظف / Staff</th><td>{{ $g->user?->name ?? '—' }}</td></tr>
            @if($g->notes)<tr><th>ملاحظات / Notes</th><td>{{ $g->notes }}</td></tr>@endif
        </table>
    </td>
    <td style="width:30%;text-align:center;vertical-align:top">
        @if($jc)<img src="{{ \App\Support\Codes::qr(route('job-cards.show', $jc), 3) }}" style="width:110px"><div class="muted">امسح للتحقق<br>Scan to verify</div>@endif
    </td>
</tr></table>
<table class="signs" style="margin-top:24px"><tr>
    <td><div class="sign"></div>حارس البوابة / Gate guard</td>
    <td><div class="sign"></div>{{ $g->direction === 'in' ? 'السائق / Driver' : 'المستلم / Collected by' }}</td>
</tr></table>
@endsection
