@extends('layouts.print')
@section('title', $jc->number)
@section('content')
<div class="doc-title">أمر عمل <span class="bilingual">Job Card</span> — {{ $jc->number }}</div>
<table class="grid" style="margin-bottom:8px">
    <tr><th>العميل / Customer</th><td>{{ $jc->customer->name }} · {{ $jc->customer->phone }}</td><th>الحالة / Status</th><td>{{ status_label($jc->status) }}</td></tr>
    <tr><th>المركبة / Vehicle</th><td>{{ $jc->vehicle->plate() }} · {{ $jc->vehicle->title() }}</td><th>VIN</th><td dir="ltr">{{ $jc->vehicle->vin }}</td></tr>
    <tr><th>الاستلام / Received</th><td>{{ $jc->received_at->format('Y-m-d H:i') }}</td><th>التسليم / Promised</th><td>{{ $jc->promised_at?->format('Y-m-d H:i') }}</td></tr>
    <tr><th>القسم / Dept.</th><td>{{ $jc->department?->displayName() }}</td><th>المستشار / Advisor</th><td>{{ $jc->advisor?->name }}</td></tr>
    <tr><th>العداد / Odometer</th><td>{{ number_format($jc->odometer_in) }}</td><th>الأولوية / Priority</th><td>{{ status_label($jc->priority) }}</td></tr>
</table>
<div class="box"><b>شكوى العميل / Complaint:</b> {!! nl2br(e($jc->customer_complaint)) !!}</div>
@if($jc->diagnosis)<div class="box" style="margin-top:6px"><b>التشخيص / Diagnosis:</b> {!! nl2br(e($jc->diagnosis)) !!}</div>@endif
<h4>الأعمال / Tasks</h4>
<table class="grid">
    <tr><th>#</th><th>الوصف / Description</th><th>الفني / Technician</th><th>الحالة / Status</th><th class="num">الوقت (د) / Min</th></tr>
    @foreach($jc->tasks as $i => $t)<tr><td>{{ $i + 1 }}</td><td>{{ $t->description }}</td><td>{{ $t->technician?->name }}</td><td>{{ status_label($t->status) }}</td><td class="num">{{ $t->actual_minutes }}/{{ $t->estimated_minutes }}</td></tr>@endforeach
</table>
<h4>قطع الغيار / Parts</h4>
<table class="grid">
    <tr><th>#</th><th>القطعة / Part</th><th class="num">الكمية / Qty</th><th class="num">المصروف / Issued</th></tr>
    @foreach($jc->parts as $i => $p)<tr><td>{{ $i + 1 }}</td><td>{{ $p->description }} ({{ $p->part->sku }})</td><td class="num">{{ qty($p->qty) }}</td><td class="num">{{ qty($p->netQty()) }}</td></tr>@endforeach
</table>
<table style="margin-top:20px"><tr><td>توقيع الفني / Technician<div class="sign"></div></td><td>فحص الجودة / QC<div class="sign"></div></td><td>المشرف / Supervisor<div class="sign"></div></td></tr></table>
@endsection
