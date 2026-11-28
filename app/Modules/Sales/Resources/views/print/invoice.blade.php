@extends('layouts.print')
@section('title', $inv->displayNumber())
@section('pdf-url', route('invoices.print', [$inv, 'pdf']))
@section('content')
@php $company = tenant()->company(); @endphp
<div class="doc-title">{{ $inv->isPosted() ? 'فاتورة ضريبية' : 'فاتورة مبدئية' }} <span class="bilingual">{{ $inv->isPosted() ? 'Tax Invoice' : 'Proforma / Draft Invoice' }}</span></div>
<table class="head"><tr>
    <td>
        <table class="grid">
            <tr><th>رقم الفاتورة / Invoice No.</th><td><b>{{ $inv->displayNumber() }}</b></td></tr>
            <tr><th>التاريخ / Date</th><td>{{ $inv->issue_date->format('Y-m-d') }}</td></tr>
            <tr><th>تاريخ الاستحقاق / Due</th><td>{{ $inv->due_date?->format('Y-m-d') ?? $inv->issue_date->format('Y-m-d') }}</td></tr>
            <tr><th>طريقة الدفع / Terms</th><td>{{ $inv->payment_terms === 'credit' ? 'آجل / Credit' : 'نقدي / Cash' }}</td></tr>
            @if($inv->jobCard)<tr><th>أمر العمل / Job</th><td>{{ $inv->jobCard->number }} — {{ $inv->jobCard->vehicle?->plate() }} {{ $inv->jobCard->vehicle?->title() }}</td></tr>@endif
        </table>
    </td>
    <td style="width:45%">
        <table class="grid">
            <tr><th>العميل / Customer</th><td>{{ $inv->customer->name }}@if($inv->customer->name_en)<br>{{ $inv->customer->name_en }}@endif</td></tr>
            <tr><th>الهاتف / Phone</th><td dir="ltr">{{ $inv->customer->phone }}</td></tr>
            @if($inv->customer->vat_number)<tr><th>الرقم الضريبي / VATIN</th><td>{{ $inv->customer->vat_number }}</td></tr>@endif
            @if($inv->customer->cr_number)<tr><th>س.ت / C.R</th><td>{{ $inv->customer->cr_number }}</td></tr>@endif
            @if($inv->customer->address)<tr><th>العنوان / Address</th><td>{{ $inv->customer->address }}</td></tr>@endif
        </table>
    </td>
</tr></table>
<table class="grid" style="margin-top:8px">
    <tr><th>#</th><th>الوصف / Description</th><th class="num">الكمية<br>Qty</th><th class="num">سعر الوحدة<br>Unit price</th><th class="num">الخصم<br>Discount</th><th class="num">المبلغ الخاضع<br>Taxable</th><th class="num">الضريبة %<br>VAT %</th><th class="num">الضريبة<br>VAT</th><th class="num">الإجمالي<br>Total</th></tr>
    @foreach($inv->items as $i => $it)
        <tr><td>{{ $i + 1 }}</td><td>{{ $it->description }}</td><td class="num">{{ qty($it->qty) }}</td><td class="num">{{ money($it->unit_price, false) }}</td><td class="num">{{ money($it->discount, false) }}</td>
            <td class="num">{{ money($it->line_total, false) }}</td><td class="num">{{ qty($it->tax_rate) }}</td><td class="num">{{ money($it->tax_amount, false) }}</td><td class="num">{{ money((float) $it->line_total + (float) $it->tax_amount, false) }}</td></tr>
    @endforeach
</table>
<table style="margin-top:8px"><tr>
    <td style="width:140px;text-align:center;vertical-align:top"><img src="{{ $qr }}" style="width:120px"></td>
    <td>
        <table class="totals" style="width:70%;margin-{{ is_rtl() ? 'right' : 'left' }}:auto">
            <tr><td>الإجمالي قبل الخصم / Subtotal</td><td class="num">{{ money($inv->subtotal) }}</td></tr>
            <tr><td>الخصم / Discount</td><td class="num">-{{ money($inv->discount_total) }}</td></tr>
            <tr><td>الإجمالي الخاضع للضريبة / Taxable amount</td><td class="num">{{ money((float) $inv->subtotal - (float) $inv->discount_total) }}</td></tr>
            <tr><td>ضريبة القيمة المضافة / VAT</td><td class="num">{{ money($inv->tax_total) }}</td></tr>
            <tr class="grand"><td>الإجمالي شامل الضريبة / Total incl. VAT</td><td class="num">{{ money($inv->total) }}</td></tr>
            @if((float) $inv->paid_amount > 0)<tr><td>المدفوع / Paid</td><td class="num">{{ money($inv->paid_amount) }}</td></tr><tr><td><b>المتبقي / Balance</b></td><td class="num"><b>{{ money($inv->balanceDue()) }}</b></td></tr>@endif
            @if($inv->cash_tendered !== null && (float) $inv->cash_tendered > 0)<tr><td>المستلم نقداً / Cash received</td><td class="num">{{ money($inv->cash_tendered) }}</td></tr><tr><td><b>الباقي للعميل / Change</b></td><td class="num"><b>{{ money($inv->change_given ?? 0) }}</b></td></tr>@endif
        </table>
    </td>
</tr></table>
@if($inv->notes)<div class="box" style="margin-top:8px">{{ $inv->notes }}</div>@endif
@if(setting('invoice.terms'))<div class="muted" style="margin-top:8px">{!! nl2br(e(setting('invoice.terms'))) !!}</div>@endif
<p style="text-align:center;margin-top:14px">{{ setting('invoice.footer_ar') }}<br><span class="muted">{{ setting('invoice.footer_en') }}</span></p>
@endsection
