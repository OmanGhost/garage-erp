@extends('layouts.print')
@section('title', $po->number)
@section('content')
<div class="doc-title">أمر شراء <span class="bilingual">Purchase Order</span></div>
<table class="grid" style="margin-bottom:8px">
    <tr><th>رقم الأمر / PO No.</th><td>{{ $po->number }}</td><th>التاريخ / Date</th><td>{{ $po->order_date->format('Y-m-d') }}</td></tr>
    <tr><th>المورد / Supplier</th><td>{{ $po->supplier->name }} · {{ $po->supplier->phone }}</td><th>التسليم المتوقع / Expected</th><td>{{ $po->expected_date?->format('Y-m-d') }}</td></tr>
    <tr><th>مكان التسليم / Deliver to</th><td>{{ $po->warehouse->displayName() }}</td><th>الرقم الضريبي للمورد / VATIN</th><td>{{ $po->supplier->vat_number }}</td></tr>
</table>
<table class="grid">
    <tr><th>#</th><th>الصنف / Item</th><th>رقم القطعة / Part no.</th><th class="num">الكمية / Qty</th><th class="num">السعر / Unit</th><th class="num">الخصم / Disc.</th><th class="num">الضريبة / VAT</th><th class="num">الإجمالي / Total</th></tr>
    @foreach($po->items as $i => $it)<tr><td>{{ $i + 1 }}</td><td>{{ $it->description }}</td><td dir="ltr">{{ $it->part->oem_number ?? $it->part->sku }}</td><td class="num">{{ qty($it->qty) }}</td><td class="num">{{ money($it->unit_cost, false) }}</td><td class="num">{{ money($it->discount, false) }}</td><td class="num">{{ money($it->tax_amount, false) }}</td><td class="num">{{ money((float) $it->line_total + (float) $it->tax_amount, false) }}</td></tr>@endforeach
</table>
<table class="totals" style="width:45%;margin-{{ is_rtl() ? 'right' : 'left' }}:auto;margin-top:8px">
    <tr><td>المجموع / Subtotal</td><td class="num">{{ money((float) $po->subtotal - (float) $po->discount_total) }}</td></tr>
    <tr><td>الضريبة / VAT</td><td class="num">{{ money($po->tax_total) }}</td></tr>
    <tr class="grand"><td>الإجمالي / Total</td><td class="num">{{ money($po->total) }}</td></tr>
</table>
@if($po->terms)<div class="box" style="margin-top:8px">{!! nl2br(e($po->terms)) !!}</div>@endif
<table style="margin-top:24px"><tr><td>المعتمد / Approved by: {{ $po->approver?->name }}<div class="sign"></div></td><td>المورد / Supplier<div class="sign"></div></td></tr></table>
@endsection
