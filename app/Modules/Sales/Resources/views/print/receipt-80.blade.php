@extends('layouts.print', ['paper' => '80mm'])
@section('title', $inv->displayNumber())
@section('styles') .paper { font-size: 11px; } .r80 td { padding: 2px 0; } .c { text-align: center; } hr { border: 0; border-top: 1px dashed #333; } @endsection
@section('content')
@php $company = tenant()->company(); @endphp
<div class="c">
    @if($company->logoUrl())<img src="{{ $company->logoUrl() }}" style="max-height:40px"><br>@endif
    <b style="font-size:14px">{{ $company->name_ar ?? $company->name }}</b><br>{{ $company->name_en }}<br>
    {{ $company->phone }}<br>
    @if($company->vat_number)VATIN: {{ $company->vat_number }}<br>@endif
    @if($company->cr_number)C.R: {{ $company->cr_number }}@endif
</div>
<hr>
<div class="c"><b>{{ $inv->isPosted() ? 'فاتورة ضريبية مبسطة / Simplified Tax Invoice' : 'مسودة / Draft' }}</b></div>
<table class="r80">
    <tr><td>No.</td><td>{{ $inv->displayNumber() }}</td></tr>
    <tr><td>Date</td><td>{{ ($inv->posted_at ?? $inv->created_at)->format('Y-m-d H:i') }}</td></tr>
    <tr><td>Customer</td><td>{{ $inv->customer->name }}</td></tr>
    <tr><td>Cashier</td><td>{{ $inv->salesperson?->name }}</td></tr>
</table>
<hr>
<table class="r80">
    @foreach($inv->items as $it)
        <tr><td colspan="3">{{ $it->description }}</td></tr>
        <tr><td>{{ qty($it->qty) }} × {{ money($it->unit_price, false) }}</td><td>@if((float) $it->discount)-{{ money($it->discount, false) }}@endif</td><td class="num">{{ money($it->line_total, false) }}</td></tr>
    @endforeach
</table>
<hr>
<table class="r80">
    <tr><td>المجموع / Subtotal</td><td class="num">{{ money((float) $inv->subtotal - (float) $inv->discount_total, false) }}</td></tr>
    <tr><td>الضريبة / VAT</td><td class="num">{{ money($inv->tax_total, false) }}</td></tr>
    <tr><td><b>الإجمالي / TOTAL</b></td><td class="num"><b>{{ money($inv->total) }}</b></td></tr>
    <tr><td>المدفوع / Paid</td><td class="num">{{ money($inv->paid_amount, false) }}</td></tr>
    @if($inv->cash_tendered !== null && (float) $inv->cash_tendered > 0)
        <tr><td>المستلم نقداً / Cash received</td><td class="num">{{ money($inv->cash_tendered, false) }}</td></tr>
        <tr><td><b>الباقي للعميل / CHANGE</b></td><td class="num"><b>{{ money($inv->change_given ?? 0, false) }}</b></td></tr>
    @endif
    @if(\App\Support\Money::isPositive($inv->balanceDue()))<tr><td><b>المتبقي على العميل / Balance due</b></td><td class="num"><b>{{ money($inv->balanceDue(), false) }}</b></td></tr>@endif
</table>
<hr>
<div class="c"><img src="{{ $qr }}" style="width:110px"><br>{{ setting('invoice.footer_ar') }}<br>{{ setting('invoice.footer_en') }}</div>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
@endsection
