@extends('layouts.print')
@section('title', __('Inspection report'))
@section('pdf-url', route('inspections.print', [$inspection, 'format' => 'pdf']))
@section('content')
@php
    $jc = $inspection->jobCard;
    $Z = \App\Modules\Workshop\Models\Inspection::class;
    $cnt = $inspection->lightCounts();
    $lc = ['green' => '#22c55e', 'yellow' => '#eab308', 'red' => '#ef4444', 'grey' => '#adb5bd'];
@endphp
<div class="doc-title">تقرير الفحص الفني <span class="bilingual">Technical Inspection Report — {{ $inspection->stage === 'after' ? 'بعد الإصلاح / After repair' : 'قبل الإصلاح / Before repair' }}</span></div>
<table style="margin-bottom:8px"><tr>
    <td style="text-align:center;background:#dcfce7;padding:6px"><b style="font-size:16px">{{ $cnt['green'] }}</b><br>سليم / Good</td>
    <td style="text-align:center;background:#fef9c3;padding:6px"><b style="font-size:16px">{{ $cnt['yellow'] }}</b><br>يحتاج متابعة / Soon</td>
    <td style="text-align:center;background:#fee2e2;padding:6px"><b style="font-size:16px">{{ $cnt['red'] }}</b><br>عاجل / Urgent</td>
</tr></table>
<table class="grid" style="margin-bottom:8px">
    <tr><th>أمر العمل / Job</th><td>{{ $jc->number }}</td><th>التاريخ / Date</th><td>{{ $inspection->started_at?->format('Y-m-d H:i') }}</td></tr>
    <tr><th>المركبة / Vehicle</th><td>{{ $jc->vehicle->plate() }} · {{ $jc->vehicle->title() }}</td><th>العداد / Odometer</th><td>{{ number_format($jc->odometer_in) }}</td></tr>
    <tr><th>العميل / Customer</th><td>{{ $jc->customer->name }}</td><th>الفني / Technician</th><td>{{ $inspection->technician?->name }}</td></tr>
</table>
<table class="grid">
    <tr><th>البند / Item</th><th>النتيجة / Result</th><th>القياس / Value</th><th>ملاحظات / Notes</th></tr>
    @foreach($inspection->results->groupBy('section') as $section => $rows)
        <tr><th colspan="4" style="background:#e9ecef">{{ $section }}</th></tr>
        @foreach($rows as $r)
            <tr><td>{{ $r->label }}</td><td><span class="badge" style="background:{{ ['ok' => '#d1e7dd', 'attention' => '#fff3cd', 'urgent' => '#f8d7da', 'na' => '#eee'][$r->result] }}">{{ status_label($r->result) }}</span></td><td class="num">@if($r->value !== null){{ rtrim(rtrim((string) $r->value, '0'), '.') }} {{ $r->unit }}@endif</td><td>{{ $r->notes }}</td></tr>
        @endforeach
    @endforeach
</table>
@if($inspection->findings->isNotEmpty())
<h4>الأعطال المكتشفة / Findings</h4>
<table class="grid"><tr><th>#</th><th>المنطقة / Area</th><th>DTC</th><th>الوصف / Description</th><th>الأولوية / Priority</th><th>قرار العميل / Customer</th><th class="num">السعر / Price</th></tr>
    @foreach($inspection->findings->values() as $i => $f)<tr><td><span style="color:{{ $lc[$f->light()] }};font-size:14px">●</span> {{ $i + 1 }}</td><td>{{ $f->zone ? $Z::zoneLabel($f->zone) : '' }}</td><td dir="ltr">{{ $f->dtc_code }}</td><td>{{ $f->description }}</td><td>{{ status_label($f->severity) }}</td>
        <td>{{ $f->customer_decision === 'approved' ? 'موافق / Approved' : ($f->customer_decision === 'declined' ? 'مرفوض / Declined' : '') }}</td><td class="num">{{ (float) $f->estimated_price ? money((float) $f->estimated_price * (float) $f->qty, false) : '' }}</td></tr>@endforeach
</table>
@endif
@if($inspection->summary)<div class="box" style="margin-top:8px"><b>الملخص / Summary:</b> {!! nl2br(e($inspection->summary)) !!}</div>@endif
@if($inspection->recommendations)<div class="box" style="margin-top:8px"><b>التوصيات / Recommendations:</b> {!! nl2br(e($inspection->recommendations)) !!}</div>@endif
@php $sig = fn ($p) => \App\Modules\Workshop\Services\InspectionService::signatureData($p); @endphp
<table style="margin-top:16px"><tr>
    <td style="width:50%">توقيع الفني / Technician<br>@if($s = $sig($inspection->technician_signature))<img src="{{ $s }}" style="max-height:60px"><div class="muted">{{ $inspection->technician?->name }} · {{ $inspection->technician_signed_at?->format('Y-m-d H:i') }}</div>@else<div class="sign"></div>@endif</td>
    <td>توقيع العميل / Customer<br>@if($s = $sig($inspection->customer_signature))<img src="{{ $s }}" style="max-height:60px"><div class="muted">{{ $inspection->customer_signed_name }} · {{ $inspection->customer_signed_at?->format('Y-m-d H:i') }}</div>@else<div class="sign"></div>@endif</td>
</tr></table>
@endsection
