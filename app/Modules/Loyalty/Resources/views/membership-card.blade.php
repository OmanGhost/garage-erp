@extends('layouts.print')
@section('title', __('Membership card').' '.$m->number)
@section('styles')
    .mcard { border: 3px solid #1f6feb; border-radius: 14px; padding: 12px 14px; background: #f3f7ff; }
    .mname { font-size: 20px; font-weight: bold; color: #1f6feb; }
@endsection
@section('content')
<div class="mcard">
    <table><tr>
        <td><div class="muted">بطاقة عضوية / Membership card</div><div class="mname">{{ $m->plan?->name }}</div><div>{{ $m->plan?->name_en }}</div></td>
        <td style="width:110px;text-align:center"><img src="{{ \App\Support\Codes::qr($m->number, 3) }}" style="width:90px" alt="QR"></td>
    </tr></table>
    <table class="grid" style="margin-top:8px">
        <tr><th>العضو / Member</th><td><b>{{ $m->customer?->name }}</b></td></tr>
        <tr><th>رقم العضوية / No.</th><td>{{ $m->number }}</td></tr>
        <tr><th>المركبة / Vehicle</th><td>{{ $m->vehicle?->label() ?? 'كل المركبات / All vehicles' }}</td></tr>
        <tr><th>الصلاحية / Valid</th><td>{{ $m->starts_at?->format('Y-m-d') }} → {{ $m->ends_at?->format('Y-m-d') }}</td></tr>
        <tr><th>المزايا / Benefits</th><td>
            @foreach($m->plan?->items ?? [] as $i){{ $i->service?->displayName() }} × {{ $i->qty }}<br>@endforeach
            @if((float) $m->plan?->parts_discount)خصم القطع / Parts −{{ rtrim(rtrim((string) $m->plan->parts_discount, '0'), '.') }}%<br>@endif
            @if((float) $m->plan?->labor_discount)خصم العمالة / Labour −{{ rtrim(rtrim((string) $m->plan->labor_discount, '0'), '.') }}%<br>@endif
            @if($m->plan?->priority_booking)أولوية الحجز / Priority booking<br>@endif
            @if($m->plan?->roadside_calls)مساعدة على الطريق / Roadside × {{ $m->plan->roadside_calls }}@endif
        </td></tr>
    </table>
</div>
@endsection
