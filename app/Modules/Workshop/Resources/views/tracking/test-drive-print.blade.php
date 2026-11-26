@extends('layouts.print')
@section('title', __('Test-drive permit').' '.$td->number)
@section('content')
<div class="doc-title">تصريح تجربة مركبة <span class="bilingual">Test-drive permit</span></div>
<table class="grid">
    <tr><th style="width:38%">رقم التصريح / Permit No.</th><td><b>{{ $td->number }}</b> · {{ status_label($td->status) }}</td></tr>
    <tr><th>المركبة / Vehicle</th><td><b>{{ $td->vehicle?->plate() }}</b> — {{ $td->vehicle?->title() }}</td></tr>
    <tr><th>أمر العمل / Job card</th><td>{{ $td->jobCard?->number }} · {{ $td->jobCard?->customer?->name }}</td></tr>
    <tr><th>السائق / Driver</th><td>{{ $td->driver?->name }}</td></tr>
    <tr><th>صرّح بها / Authorised by</th><td>{{ $td->authorizer?->name }} · {{ $td->created_at?->format('Y-m-d H:i') }}</td></tr>
    <tr><th>الغرض / Purpose</th><td>{{ $td->purpose ?? '—' }}</td></tr>
    <tr><th>المسار / Route</th><td>{{ $td->route ?? '—' }}</td></tr>
    <tr><th>الحد الأقصى / Max distance</th><td>{{ $td->max_km ? $td->max_km.' km' : '—' }}</td></tr>
    <tr><th>موافقة العميل / Customer consent</th><td>{{ $td->customer_consent ? 'نعم / Yes' : 'لا / No' }}</td></tr>
</table>
<table class="grid" style="margin-top:8px">
    <tr><th></th><th>العداد / Odometer</th><th>الوقود / Fuel</th><th>الوقت / Time</th></tr>
    <tr><th>خروج / Out</th><td class="num">{{ $td->odometer_out !== null ? number_format($td->odometer_out).' km' : '' }}</td><td class="num">{{ $td->fuel_out !== null ? $td->fuel_out.'%' : '' }}</td><td>{{ $td->out_at?->format('H:i') }}</td></tr>
    <tr><th>عودة / In</th><td class="num">{{ $td->odometer_in !== null ? number_format($td->odometer_in).' km' : '' }}</td><td class="num">{{ $td->fuel_in !== null ? $td->fuel_in.'%' : '' }}</td><td>{{ $td->in_at?->format('H:i') }}</td></tr>
    <tr><th>المسافة / Distance</th><td class="num" colspan="3"><b>{{ $td->distance() !== null ? $td->distance().' km' : '' }}</b>@if($td->overLimit()) — تجاوز الحد / OVER LIMIT @endif</td></tr>
</table>
@if($td->notes)<div class="box" style="margin-top:8px;white-space:pre-line">{{ $td->notes }}</div>@endif
<table style="margin-top:22px"><tr>
    <td style="width:50%;text-align:center"><div class="sign"></div>توقيع السائق / Driver</td>
    <td style="text-align:center"><div class="sign"></div>توقيع المسؤول / Supervisor</td>
</tr></table>
@endsection
