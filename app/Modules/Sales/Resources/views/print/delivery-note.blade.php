@extends('layouts.print')
@section('title', preg_replace('/^[A-Z]+-/', 'DN-', $inv->displayNumber()))
@section('pdf-url', route('invoices.delivery-note', [$inv, 'pdf']))
@section('styles')
    .signs td { width: 33%; text-align: center; vertical-align: bottom; padding: 0 8px; }
    .stamp { text-align: center; font-weight: bold; padding: 5px; border-radius: 6px; margin: 6px 0; border: 2px solid #b02a37; color: #b02a37; }
@endsection
@section('content')
@php
    $vehicle = $inv->jobCard?->vehicle;
    $dn = preg_match('/^[A-Z]+-/', $inv->displayNumber()) ? preg_replace('/^[A-Z]+-/', 'DN-', $inv->displayNumber()) : 'DN-'.$inv->displayNumber();
@endphp
<div class="doc-title">إشعار تسليم <span class="bilingual">Delivery Note</span></div>
@unless($inv->isPosted())
    <div class="stamp">مسودة — الفاتورة غير مرحّلة / DRAFT — invoice not posted</div>
@endunless

<table class="head"><tr>
    <td>
        <table class="grid">
            <tr><th>رقم الإشعار / Delivery note No.</th><td><b>{{ $dn }}</b></td></tr>
            <tr><th>التاريخ / Date</th><td>{{ $inv->issue_date->format('Y-m-d') }}</td></tr>
            <tr><th>الفاتورة / Invoice</th><td>{{ $inv->displayNumber() }}</td></tr>
            @if($inv->warehouse)<tr><th>المستودع / Warehouse</th><td>{{ $inv->warehouse->displayName() }}</td></tr>@endif
            @if($inv->jobCard)<tr><th>أمر العمل / Job card</th><td>{{ $inv->jobCard->number }}@if($vehicle) — <span dir="ltr">{{ $vehicle->plate() }}</span> {{ $vehicle->title() }}@endif</td></tr>@endif
        </table>
    </td>
    <td style="width:45%">
        <table class="grid">
            <tr><th>العميل / Customer</th><td>{{ $inv->customer->name }}@if($inv->customer->name_en)<br>{{ $inv->customer->name_en }}@endif</td></tr>
            <tr><th>الهاتف / Phone</th><td dir="ltr">{{ $inv->customer->phone }}</td></tr>
            @if($inv->customer->address)<tr><th>العنوان / Address</th><td>{{ $inv->customer->address }}</td></tr>@endif
        </table>
    </td>
</tr></table>

<table class="grid" style="margin-top:8px">
    <tr><th>#</th><th>رقم الصنف / Item code</th><th>الوصف / Description</th><th class="num">الكمية<br>Qty</th><th class="num">المرتجع<br>Returned</th><th class="num">المسلّم<br>Delivered</th><th>الوحدة<br>Unit</th></tr>
    @forelse($goods as $i => $it)
        @php $delivered = max(0, (float) $it->qty - (float) $it->returned_qty); @endphp
        <tr><td>{{ $loop->iteration }}</td><td dir="ltr">{{ $it->part?->sku ?? '—' }}</td><td>{{ $it->description }}</td>
            <td class="num">{{ qty($it->qty) }}</td><td class="num">{{ (float) $it->returned_qty > 0 ? qty($it->returned_qty) : '—' }}</td>
            <td class="num"><b>{{ qty($delivered) }}</b></td><td>{{ $it->part?->unit ? status_label($it->part->unit) : '—' }}</td></tr>
    @empty
        <tr><td colspan="7" style="text-align:center">لا توجد أصناف للتسليم في هذه الفاتورة / No goods to deliver on this invoice</td></tr>
    @endforelse
    @if($goods->isNotEmpty())
        <tr><th colspan="5" style="text-align:{{ is_rtl() ? 'left' : 'right' }}">إجمالي الكمية المسلّمة / Total delivered</th>
            <th class="num">{{ qty($goods->sum(fn ($it) => max(0, (float) $it->qty - (float) $it->returned_qty))) }}</th><th></th></tr>
    @endif
</table>
@if($services > 0)<p class="muted">لا يتضمن هذا الإشعار الخدمات والأعمال ({{ $services }}) / Services and labour lines ({{ $services }}) are not part of this delivery note.</p>@endif
@if($inv->notes)<p class="muted">ملاحظات / Notes: {{ $inv->notes }}</p>@endif

<p class="muted" style="margin-top:8px">
    استلمت الأصناف المذكورة أعلاه بحالة جيدة وبالكميات الموضحة.<br>
    I received the items listed above in good condition and in the quantities shown.
</p>

<table class="signs" style="margin-top:22px"><tr>
    <td><div class="sign"></div>أمين المستودع / Storekeeper</td>
    <td><div class="sign"></div>سلّمها / Delivered by<br><span class="muted">{{ $inv->creator?->name }}</span></td>
    <td><div class="sign"></div>المستلم (الاسم والتوقيع والتاريخ)<br>Received by (name, signature, date)</td>
</tr></table>
@endsection
