@extends('layouts.print')
@section('title', 'طلب تغيير صلاحية '.$r->number)
@section('styles')
    .stamp { text-align: center; font-weight: bold; font-size: 15px; padding: 8px; border-radius: 6px; margin: 10px 0; }
    .stamp.ok { border: 2px solid #198754; color: #198754; }
    .stamp.no { border: 2px solid #b02a37; color: #b02a37; }
    .stamp.wait { border: 2px dashed #8a6d00; color: #8a6d00; }
    .box { border: 1px solid #ccc; border-radius: 4px; padding: 8px; min-height: 60px; white-space: pre-wrap; }
    .signs td { width: 33%; text-align: center; vertical-align: bottom; padding: 0 6px; font-size: 11px; }
    .signs .sign { height: 55px; border-bottom: 1px solid #999; margin-bottom: 4px; }
@endsection
@section('content')
@php
    $roleAr = fn ($role) => $role ? trans($role, [], 'ar') : '—';
    $roleEn = fn ($role) => $role ? trans($role, [], 'en') : '—';
@endphp
<div class="doc-title">طلب تغيير صلاحية مستخدم <span class="bilingual">User Role Change Request</span></div>

@if($r->status === 'approved')
    <div class="stamp ok">تمت الموافقة / APPROVED — {{ $r->decided_at?->format('Y-m-d H:i') }}</div>
@elseif($r->status === 'rejected')
    <div class="stamp no">مرفوض / REJECTED — {{ $r->decided_at?->format('Y-m-d H:i') }}</div>
@elseif($r->status === 'cancelled')
    <div class="stamp no">ملغى / CANCELLED</div>
@else
    <div class="stamp wait">بانتظار الموافقة / PENDING APPROVAL</div>
@endif

<table class="grid">
    <tr><th style="width:35%">رقم الطلب / Request No.</th><td><b>{{ $r->number }}</b></td></tr>
    <tr><th>تاريخ الطلب / Date</th><td>{{ $r->created_at->format('Y-m-d H:i') }}</td></tr>
    <tr><th>الموظف / User</th><td><b>{{ $r->user?->name }}</b> · <span dir="ltr">{{ $r->user?->email }}</span></td></tr>
    <tr><th>الفرع / Branch</th><td>{{ $r->branch?->displayName() ?? '—' }}</td></tr>
    <tr><th>الصلاحية الحالية / Current role</th><td>{{ $roleAr($r->current_role) }} / {{ $roleEn($r->current_role) }}</td></tr>
    <tr><th>الصلاحية المطلوبة / Requested role</th><td><b>{{ $roleAr($r->requested_role) }} / {{ $roleEn($r->requested_role) }}</b></td></tr>
    @if($r->effective_date)<tr><th>تاريخ السريان / Effective date</th><td>{{ $r->effective_date->format('Y-m-d') }}</td></tr>@endif
    <tr><th>مقدم الطلب / Requested by</th><td>{{ $r->requester?->name }}</td></tr>
</table>

<p style="margin:10px 0 4px"><b>أسباب الطلب / Reason</b></p>
<div class="box">{{ $r->reason }}</div>

<p style="margin:10px 0 4px"><b>القرار / Decision</b></p>
<div class="box">@if($r->decided_at){{ $r->status === 'approved' ? 'موافقة / Approved' : 'رفض / Rejected' }} — {{ $r->decider?->name }} — {{ $r->decided_at->format('Y-m-d H:i') }}@if($r->decision_notes)

{{ $r->decision_notes }}@endif @else &nbsp; @endif</div>

<table class="signs" style="width:100%; margin-top:30px"><tr>
    <td><div class="sign"></div>مقدم الطلب / Requested by<br>{{ $r->requester?->name }}</td>
    <td><div class="sign"></div>صاحب الصلاحية / Approved by<br>{{ $r->decider?->name }}</td>
    <td><div class="sign"></div>الموظف المعني / Employee<br>{{ $r->user?->name }}</td>
</tr></table>
@endsection
