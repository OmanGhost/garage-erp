@extends('layouts.print')
@section('title', $c->number)
@section('pdf-url', route('warranty.claims.print', [$c, 'pdf']))
@section('styles')
    .signs td { width: 33%; text-align: center; vertical-align: bottom; padding: 0 6px; }
@endsection
@section('content')
@php
    $w = $c->warranty;
    $res = ['replace' => 'استبدال القطعة / Replace part', 'repair' => 'إعادة الإصلاح / Repair again', 'refund' => 'استرداد المبلغ / Refund', 'reject' => 'رفض / Rejected'];
@endphp
<div class="doc-title">مطالبة ضمان <span class="bilingual">Warranty Claim</span></div>
<table class="grid" style="margin-bottom:8px">
    <tr><th>رقم المطالبة / Claim No.</th><td><b>{{ $c->number }}</b></td><th>التاريخ / Date</th><td>{{ $c->claim_date?->format('Y-m-d') }}</td></tr>
    <tr><th>العميل / Customer</th><td>{{ $w->customer?->name }} · <span dir="ltr">{{ $w->customer?->phone }}</span></td><th>الحالة / Status</th><td><b>{{ status_label($c->status) }}</b></td></tr>
    <tr><th>المركبة / Vehicle</th><td>{{ $w->vehicle?->label() ?? '—' }}</td><th>العداد / Odometer</th><td>{{ $c->odometer ? number_format($c->odometer).' km' : '—' }}</td></tr>
</table>
<table class="grid" style="margin-bottom:8px">
    <tr><th colspan="4">الضمان / Warranty</th></tr>
    <tr><th>البند / Item</th><td>{{ $w->description }}@if($w->part) — <span dir="ltr">{{ $w->part->sku }}</span>@endif</td><th>الرقم التسلسلي / Serial</th><td dir="ltr">{{ $w->serial_number ?: '—' }}</td></tr>
    <tr><th>المدة / Period</th><td>{{ $w->start_date?->format('Y-m-d') }} → {{ $w->end_date?->format('Y-m-d') }}</td><th>حد المسافة / Km limit</th><td>{{ $w->km_limit ? number_format($w->km_limit).' km' : '—' }}</td></tr>
    <tr><th>الفاتورة / Invoice</th><td>{{ $w->invoice?->displayNumber() ?? '—' }}</td><th>المورد / Supplier</th><td>{{ $w->supplier?->name ?? '—' }}</td></tr>
</table>
<table class="grid">
    <tr><th>وصف المشكلة / Problem reported</th></tr><tr><td>{!! nl2br(e($c->description)) !!}</td></tr>
    <tr><th>نتيجة الفحص / Inspection result</th></tr><tr><td>{!! nl2br(e($c->inspection_result ?: '—')) !!}</td></tr>
    <tr><th>القرار / Resolution</th></tr><tr><td>{{ $c->resolution ? ($res[$c->resolution] ?? $c->resolution) : '—' }}
        @if($c->reworkJobCard) · أمر عمل إعادة الإصلاح / Rework job: {{ $c->reworkJobCard->number }} ({{ status_label($c->reworkJobCard->status) }})@endif</td></tr>
</table>
<table class="grid" style="margin-top:8px">
    <tr><th>تكلفة الضمان / Warranty cost</th><td class="num">{{ money($c->cost) }}</td><th>مطالبة المورد / Supplier claim</th><td>{{ $c->supplier_claim ? status_label((string) $c->supplier_claim_status ?: 'submitted') : '—' }}</td></tr>
    <tr><th>المسترد من المورد / Recovered</th><td class="num">{{ money($c->supplier_recovered_amount) }}</td><th>صافي التكلفة / Net cost</th><td class="num"><b>{{ money((float) $c->cost - (float) $c->supplier_recovered_amount) }}</b></td></tr>
</table>
@if($c->events->isNotEmpty())
    <table class="grid" style="margin-top:8px"><tr><th colspan="3">سجل المتابعة / Tracking history</th></tr>
        @foreach($c->events->sortBy('id') as $e)<tr><td style="width:22%">{{ $e->created_at?->format('Y-m-d H:i') }}</td><td style="width:20%">{{ $e->user?->name ?? '—' }}</td><td>{{ $labels($e) }}@if($e->notes) — {{ $e->notes }}@endif</td></tr>@endforeach
    </table>
@endif
<table class="signs" style="margin-top:24px"><tr>
    <td><div class="sign"></div>الفني المختص / Technician</td>
    <td><div class="sign"></div>مدير الخدمة / Service manager<br><span class="muted">{{ $c->creator?->name }}</span></td>
    <td><div class="sign"></div>العميل / Customer</td>
</tr></table>
@endsection
