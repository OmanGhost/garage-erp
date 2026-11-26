@extends('layouts.print')
@section('title', $c->displayNumber())
@section('pdf-url', route('complaints.print', [$c, 'pdf']))
@section('styles')
    .signs td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 8px; }
@endsection
@section('content')
<div class="doc-title">نموذج شكوى / ملاحظة عميل <span class="bilingual">Customer Complaint / Feedback</span></div>
<table class="grid" style="margin-bottom:8px">
    <tr><th>رقم المرجع / Reference</th><td><b>{{ $c->displayNumber() }}</b></td><th>التاريخ / Date</th><td>{{ $c->created_at?->format('Y-m-d H:i') }}</td></tr>
    <tr><th>العميل / Customer</th><td>{{ $c->customer?->name }} · <span dir="ltr">{{ $c->customer?->phone }}</span></td><th>المصدر / Source</th><td>{{ status_label((string) $c->source) }}</td></tr>
    <tr><th>أمر العمل / Job card</th><td>{{ $c->jobCard?->number ?? '—' }}</td><th>المركبة / Vehicle</th><td>{{ $c->vehicle?->label() ?? $c->jobCard?->vehicle?->label() ?? '—' }}</td></tr>
    <tr><th>الأولوية / Priority</th><td>{{ status_label((string) $c->priority) }}</td><th>الحالة / Status</th><td><b>{{ status_label((string) $c->status) }}</b></td></tr>
    <tr><th>المسؤول / Assigned to</th><td>{{ $c->assignee?->name ?? '—' }}</td><th>تاريخ الحل / Resolved</th><td>{{ $c->resolved_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
</table>
<table class="grid">
    <tr><th>الموضوع / Subject</th></tr>
    <tr><td><b>{{ $c->subject }}</b></td></tr>
    <tr><th>تفاصيل الشكوى / Details</th></tr>
    <tr><td>{!! nl2br(e($c->description ?: '—')) !!}</td></tr>
    <tr><th>الإجراء المتخذ / الحل — Action taken / resolution</th></tr>
    <tr><td>{!! nl2br(e($c->resolution ?: '—')) !!}</td></tr>
</table>
@if($history->isNotEmpty())
    <table class="grid" style="margin-top:8px">
        <tr><th colspan="3">سجل المتابعة / Tracking history</th></tr>
        @foreach($history as $h)<tr><td style="width:24%">{{ $h['at'] }}</td><td style="width:20%">{{ $h['user'] }}</td><td>{{ $h['type'] === 'created' ? __('Complaint registered') : $h['text'] }}</td></tr>@endforeach
    </table>
@endif
<table class="signs" style="margin-top:26px"><tr>
    <td><div class="sign"></div>توقيع الموظف / Staff signature</td>
    <td><div class="sign"></div>توقيع العميل / Customer signature</td>
</tr></table>
@endsection
