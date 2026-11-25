@extends('layouts.print')
@section('title', __('Inspection report'))
@section('pdf-url', route('inspections.print', [$inspection, 'format' => 'pdf']))
@section('content')
@php $jc = $inspection->jobCard; @endphp
<div class="doc-title">تقرير الفحص الفني <span class="bilingual">Technical Inspection Report</span></div>
<table class="grid" style="margin-bottom:8px">
    <tr><th>أمر العمل / Job</th><td>{{ $jc->number }}</td><th>التاريخ / Date</th><td>{{ $inspection->started_at?->format('Y-m-d H:i') }}</td></tr>
    <tr><th>المركبة / Vehicle</th><td>{{ $jc->vehicle->plate() }} · {{ $jc->vehicle->title() }}</td><th>العداد / Odometer</th><td>{{ number_format($jc->odometer_in) }}</td></tr>
    <tr><th>العميل / Customer</th><td>{{ $jc->customer->name }}</td><th>الفني / Technician</th><td>{{ $inspection->technician?->name }}</td></tr>
</table>
<table class="grid">
    <tr><th>البند / Item</th><th>النتيجة / Result</th><th>ملاحظات / Notes</th></tr>
    @foreach($inspection->results->groupBy('section') as $section => $rows)
        <tr><th colspan="3" style="background:#e9ecef">{{ $section }}</th></tr>
        @foreach($rows as $r)
            <tr><td>{{ $r->label }}</td><td><span class="badge" style="background:{{ ['ok' => '#d1e7dd', 'attention' => '#fff3cd', 'urgent' => '#f8d7da', 'na' => '#eee'][$r->result] }}">{{ status_label($r->result) }}</span></td><td>{{ $r->notes }}</td></tr>
        @endforeach
    @endforeach
</table>
@if($inspection->findings->isNotEmpty())
<h4>الأعطال المكتشفة / Findings</h4>
<table class="grid"><tr><th>#</th><th>DTC</th><th>الوصف / Description</th><th>الخطورة / Severity</th></tr>
    @foreach($inspection->findings as $i => $f)<tr><td>{{ $i + 1 }}</td><td dir="ltr">{{ $f->dtc_code }}</td><td>{{ $f->description }}</td><td>{{ status_label($f->severity) }}</td></tr>@endforeach
</table>
@endif
@if($inspection->summary)<div class="box" style="margin-top:8px"><b>الملخص / Summary:</b> {!! nl2br(e($inspection->summary)) !!}</div>@endif
@if($inspection->recommendations)<div class="box" style="margin-top:8px"><b>التوصيات / Recommendations:</b> {!! nl2br(e($inspection->recommendations)) !!}</div>@endif
<table style="margin-top:16px"><tr><td>توقيع الفني / Technician<div class="sign"></div></td><td>توقيع المشرف / Supervisor<div class="sign"></div></td></tr></table>
@endsection
