@extends('layouts.print')
@section('title', $a->number)
@section('pdf-url', route('handover-authorizations.print', [$a, 'pdf']))
@section('styles')
    .signs td { width: 33%; text-align: center; vertical-align: bottom; padding: 0 8px; }
    .stamp { text-align: center; font-weight: bold; padding: 5px; border-radius: 6px; margin: 6px 0; }
    .stamp.ok { border: 2px solid #198754; color: #198754; }
    .stamp.no { border: 2px solid #b02a37; color: #b02a37; }
@endsection
@section('content')
@php
    $types = ['drop_off' => 'تسليم المركبة للورشة / Drop off', 'pick_up' => 'استلام المركبة من الورشة / Pick up', 'both' => 'التسليم والاستلام / Drop off & pick up'];
    $v = $a->vehicle;
@endphp
<div class="doc-title">تفويض تسليم / استلام مركبة <span class="bilingual">Vehicle Handover Authorization</span></div>
@if($a->status === 'active' && $a->isValidNow())
    <div class="stamp ok">تفويض ساري / VALID AUTHORIZATION</div>
@else
    <div class="stamp no">{{ status_label($a->effectiveStatus()) }} / {{ strtoupper(str_replace('_', ' ', $a->effectiveStatus())) }}</div>
@endif
<table class="grid" style="margin-bottom:8px">
    <tr><th>رقم التفويض / Authorization No.</th><td><b>{{ $a->number }}</b></td><th>التاريخ / Date</th><td>{{ $a->created_at?->format('Y-m-d') }}</td></tr>
    <tr><th>المالك / Owner</th><td>{{ $a->customer->name }} · <span dir="ltr">{{ $a->customer->phone }}</span></td><th>هوية المالك / Owner ID</th><td>{{ $a->customer->national_id ?: '—' }}</td></tr>
    <tr><th>المركبة / Vehicle</th><td><span dir="ltr">{{ $v->plate() }}</span> {{ $v->title() }}</td><th>أمر العمل / Job card</th><td>{{ $a->jobCard?->number ?? 'كل الزيارات خلال المدة / any visit in the period' }}</td></tr>
</table>
<p style="margin:8px 0">
    أنا الموقع أدناه مالك المركبة الموضحة أعلاه، أفوّض الشخص المذكور أدناه بـ <b>{{ $types[$a->type] ?? $a->type }}</b> نيابةً عني، وأتحمل كامل المسؤولية عن ذلك.<br>
    <span class="muted">I, the undersigned owner of the vehicle above, authorise the person below to {{ ['drop_off' => 'drop off', 'pick_up' => 'pick up', 'both' => 'drop off and pick up'][$a->type] ?? $a->type }} the vehicle on my behalf and accept full responsibility.</span>
</p>
<table class="grid">
    <tr><th>اسم المفوَّض / Authorized person</th><td><b>{{ $a->person_name }}</b></td></tr>
    <tr><th>رقم الهوية / ID number</th><td>{{ $a->id_type ? status_label($a->id_type).' — ' : '' }}<span dir="ltr">{{ $a->id_number }}</span></td></tr>
    <tr><th>الهاتف / Phone</th><td dir="ltr">{{ $a->person_phone ?: '—' }}</td></tr>
    <tr><th>الصفة / Relationship</th><td>{{ $a->relationship ? status_label($a->relationship) : '—' }}</td></tr>
    <tr><th>مدة التفويض / Valid</th><td>{{ $a->valid_from?->format('Y-m-d H:i') }} — {{ $a->valid_until?->format('Y-m-d H:i') ?? 'حتى الاستخدام / until used' }}</td></tr>
    <tr><th>الاستخدام / Usage</th><td>{{ $a->single_use ? 'مرة واحدة / single use' : 'متعدد خلال المدة / multiple within the period' }} · {{ $a->uses_count }} {{ $a->uses_count === 1 ? 'استخدام / use' : 'استخدامات / uses' }}</td></tr>
    <tr><th>طريقة الاعتماد / Approved by owner</th><td>{{ $a->approved_at ? ($a->approval_method === 'code' ? 'رمز تحقق / verification code' : 'توقيع / signature').' — '.$a->approved_at->format('Y-m-d H:i') : 'بانتظار اعتماد المالك / awaiting owner approval' }}</td></tr>
</table>
@if($a->notes)<p class="muted" style="margin-top:6px">ملاحظات / Notes: {{ $a->notes }}</p>@endif
<p class="muted" style="margin-top:8px">يجب على المفوَّض إبراز بطاقة الهوية الأصلية عند التسليم أو الاستلام. / The authorized person must show the original ID card at handover.</p>
<table class="signs" style="margin-top:26px"><tr>
    <td><div class="sign"></div>توقيع المالك / Owner signature</td>
    <td><div class="sign"></div>توقيع المفوَّض / Authorized person</td>
    <td><div class="sign"></div>الموظف / Staff<br><span class="muted">{{ $a->creator?->name }}</span></td>
</tr></table>
@endsection
