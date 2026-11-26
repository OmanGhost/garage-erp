@extends('layouts.print')
@section('title', $cn->displayNumber())
@section('pdf-url', route('credit-notes.print', [$cn, 'pdf']))
@section('styles')
    .cn-stamp { display: inline-block; border: 2px solid #b02a37; color: #b02a37; border-radius: 6px; padding: 2px 10px; font-weight: bold; font-size: 12px; }
    .signs td { width: 33%; text-align: center; vertical-align: bottom; padding: 0 8px; }
@endsection
@section('content')
@php
    $posted = $cn->status === 'posted';
    $taxable = $cn->subtotal;
    $credited = $cn->creditedToAccount();
@endphp
<div class="doc-title">
    {{ $posted ? 'إشعار دائن / مرتجع مبيعات' : 'مسودة إشعار دائن' }}
    <span class="bilingual">{{ $posted ? 'Credit Note / Sales Return' : 'Draft Credit Note' }}</span>
</div>
@unless($posted)<p style="text-align:center"><span class="cn-stamp">مسودة — غير مرحّل / DRAFT — NOT POSTED</span></p>@endunless

<table class="head"><tr>
    <td>
        <table class="grid">
            <tr><th>رقم الإشعار / Credit note No.</th><td><b>{{ $cn->displayNumber() }}</b></td></tr>
            <tr><th>التاريخ / Date</th><td>{{ $cn->date->format('Y-m-d') }}</td></tr>
            <tr><th>الفاتورة الأصلية / Original invoice</th><td><b>{{ $cn->invoice->displayNumber() }}</b> — {{ $cn->invoice->issue_date->format('Y-m-d') }}</td></tr>
            <tr><th>قيمة الفاتورة / Invoice total</th><td>{{ money($cn->invoice->total) }}</td></tr>
            <tr><th>سبب الإرجاع / Reason</th><td>{{ $cn->reason ?: '—' }}</td></tr>
        </table>
    </td>
    <td style="width:45%">
        <table class="grid">
            <tr><th>العميل / Customer</th><td>{{ $cn->customer->name }}@if($cn->customer->name_en)<br>{{ $cn->customer->name_en }}@endif</td></tr>
            <tr><th>الهاتف / Phone</th><td dir="ltr">{{ $cn->customer->phone }}</td></tr>
            @if($cn->customer->vat_number)<tr><th>الرقم الضريبي / VATIN</th><td>{{ $cn->customer->vat_number }}</td></tr>@endif
            @if($cn->customer->cr_number)<tr><th>س.ت / C.R</th><td>{{ $cn->customer->cr_number }}</td></tr>@endif
            @if($cn->customer->address)<tr><th>العنوان / Address</th><td>{{ $cn->customer->address }}</td></tr>@endif
        </table>
    </td>
</tr></table>

<table class="grid" style="margin-top:8px">
    <tr><th>#</th><th>الصنف المرتجع / Returned item</th><th class="num">الكمية<br>Qty</th><th class="num">سعر الوحدة<br>Unit price</th><th class="num">الخصم<br>Discount</th><th class="num">المبلغ الخاضع<br>Taxable</th><th class="num">الضريبة %<br>VAT %</th><th class="num">الضريبة<br>VAT</th><th class="num">الإجمالي<br>Total</th></tr>
    @foreach($cn->items as $i => $it)
        <tr><td>{{ $i + 1 }}</td><td>{{ $it->description }}</td><td class="num">{{ qty($it->qty) }}</td><td class="num">{{ money($it->unit_price, false) }}</td><td class="num">{{ money($it->discount ?? 0, false) }}</td>
            <td class="num">{{ money($it->line_total, false) }}</td><td class="num">{{ qty($it->tax_rate) }}</td><td class="num">{{ money($it->tax_amount, false) }}</td><td class="num">{{ money((float) $it->line_total + (float) $it->tax_amount, false) }}</td></tr>
    @endforeach
</table>

<table style="margin-top:8px"><tr>
    <td style="vertical-align:top;width:50%">
        <table class="grid">
            <tr><th colspan="2">تسوية الإشعار / Settlement</th></tr>
            <tr><td>مُرد للعميل نقداً أو بنكياً / Refunded</td><td class="num">{{ money($cn->refunded_amount ?? 0) }}</td></tr>
            @if($cn->refundPayment)<tr><td colspan="2" class="muted">سند صرف / Payment voucher {{ $cn->refundPayment->number }} — {{ $cn->refundPayment->bankAccount?->name }} — {{ $cn->refundPayment->date?->format('Y-m-d') }}</td></tr>@endif
            <tr><td>مقيد لحساب العميل / Credited to account</td><td class="num">{{ money($credited) }}</td></tr>
            <tr><td>إرجاع للمخزون / Restocked</td><td>{{ $cn->restock ? 'نعم / Yes'.($cn->warehouse ? ' — '.$cn->warehouse->displayName() : '') : 'لا / No' }}</td></tr>
        </table>
    </td>
    <td style="vertical-align:top">
        <table class="totals" style="width:92%;margin-{{ is_rtl() ? 'right' : 'left' }}:auto">
            <tr><td>المبلغ الخاضع للضريبة / Taxable amount</td><td class="num">{{ money($taxable) }}</td></tr>
            <tr><td>ضريبة القيمة المضافة / VAT</td><td class="num">{{ money($cn->tax_total) }}</td></tr>
            <tr class="grand"><td>الإجمالي / Total</td><td class="num">{{ money($cn->total) }}</td></tr>
        </table>
    </td>
</tr></table>

<p class="muted" style="margin-top:10px">
    يُخفّض هذا الإشعار قيمة الفاتورة الأصلية رقم {{ $cn->invoice->displayNumber() }} وضريبتها المستحقة بالمبالغ المذكورة أعلاه.
    <br>This credit note reduces the original invoice {{ $cn->invoice->displayNumber() }} and its output VAT by the amounts above.
</p>

<table class="signs" style="margin-top:26px"><tr>
    <td><div class="sign"></div>أعدّه / Prepared by<br><span class="muted">{{ $cn->creator?->name }}</span></td>
    <td><div class="sign"></div>اعتمده / Approved by<br><span class="muted">{{ $cn->poster?->name }} {{ $cn->posted_at?->format('Y-m-d H:i') }}</span></td>
    <td><div class="sign"></div>توقيع العميل / Customer signature<br><span class="muted">&nbsp;</span></td>
</tr></table>
@endsection
