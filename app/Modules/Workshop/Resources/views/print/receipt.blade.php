@extends('layouts.print')
@section('title', __('Vehicle reception receipt').' '.$jc->number)
@section('content')
@php $ci = $jc->checkIn; $url = $ci ? route('public.track', $ci->receipt_token) : route('job-cards.show', $jc); @endphp
<div class="doc-title">إيصال استلام مركبة <span class="bilingual">Vehicle Reception Receipt</span></div>
<table class="head">
    <tr>
        <td>
            <table class="grid">
                <tr><th>رقم أمر العمل / Job No.</th><td><b>{{ $jc->number }}</b></td></tr>
                <tr><th>تاريخ الاستلام / Received</th><td>{{ $ci?->received_at?->format('Y-m-d H:i') ?? $jc->received_at->format('Y-m-d H:i') }}</td></tr>
                <tr><th>التسليم المتوقع / Expected</th><td>{{ $jc->promised_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
                <tr><th>العميل / Customer</th><td>{{ $jc->customer->name }} · <span dir="ltr">{{ $jc->customer->phone }}</span></td></tr>
                <tr><th>المركبة / Vehicle</th><td>{{ $jc->vehicle->plate() }} ({{ $jc->vehicle->plate_country }}) · {{ $jc->vehicle->title() }}</td></tr>
                <tr><th>رقم الهيكل / VIN</th><td dir="ltr">{{ $jc->vehicle->vin ?? '—' }}</td></tr>
                <tr><th>العداد / Odometer</th><td>{{ number_format($ci?->odometer ?? $jc->odometer_in) }} km</td></tr>
                <tr><th>الوقود / Fuel</th><td>{{ $ci?->fuel_level ?? $jc->fuel_level }}%</td></tr>
                <tr><th>المستلم / Received by</th><td>{{ $ci?->receiver?->name ?? $jc->advisor?->name }}</td></tr>
            </table>
        </td>
        <td style="width:150px;text-align:center">
            <img src="{{ \App\Support\Codes::qr($url, 4) }}" style="width:140px"><div class="muted">امسح لمتابعة الحالة<br>Scan to track</div>
        </td>
    </tr>
</table>
<div class="box" style="margin-top:8px"><b>الأعطال المبلغ عنها / Reported issues:</b><br>{!! nl2br(e($ci?->reported_issues ?? $jc->customer_complaint)) !!}</div>
@if($ci && $ci->existing_damages)
<div class="box" style="margin-top:8px"><b>الأضرار الموجودة / Existing damages:</b>
    <ol style="margin:4px 0">@foreach($ci->existing_damages as $d)<li>{{ status_label($d['type'] ?? '') }} — {{ $d['area'] ?? '' }} {{ $d['description'] ?? '' }}</li>@endforeach</ol></div>
@endif
@if($ci && $ci->personal_items)
<div class="box" style="margin-top:8px"><b>الأغراض الشخصية / Personal items:</b> {{ implode('، ', $ci->personal_items) }}</div>
@endif
<p class="muted" style="margin-top:10px">
    أقر بأن البيانات أعلاه صحيحة وأفوض الورشة بفحص المركبة. لا تتحمل الورشة مسؤولية الأغراض غير المذكورة.<br>
    I confirm the above details and authorize the workshop to inspect the vehicle. The workshop is not responsible for items not listed.
</p>
<table style="margin-top:10px"><tr>
    <td style="width:50%">توقيع العميل / Customer signature<br>
        @if($ci?->signature_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($ci->signature_path))
            <img src="data:image/png;base64,{{ base64_encode(\Illuminate\Support\Facades\Storage::disk('local')->get($ci->signature_path)) }}" style="max-height:70px">
            <div class="muted">{{ $ci->customer_signed_name }}</div>
        @else <div class="sign"></div> @endif
    </td>
    <td>توقيع الموظف / Staff signature<div class="sign"></div></td>
</tr></table>
@endsection
