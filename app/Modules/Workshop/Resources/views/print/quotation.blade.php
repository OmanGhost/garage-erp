@extends('layouts.print')
@section('title', $q->number)
@section('pdf-url', route('quotations.print', [$q, 'format' => 'pdf']))
@section('content')
<div class="doc-title">عرض سعر <span class="bilingual">Quotation</span></div>
<table class="grid" style="margin-bottom:8px">
    <tr><th>رقم العرض / No.</th><td>{{ $q->number }} (v{{ $q->version }})</td><th>التاريخ / Date</th><td>{{ $q->created_at->format('Y-m-d') }}</td></tr>
    <tr><th>العميل / Customer</th><td>{{ $q->customer->name }} · {{ $q->customer->phone }}</td><th>صالح حتى / Valid until</th><td>{{ $q->valid_until?->format('Y-m-d') }}</td></tr>
    @if($q->vehicle)<tr><th>المركبة / Vehicle</th><td>{{ $q->vehicle->plate() }} · {{ $q->vehicle->title() }}</td><th>VIN</th><td dir="ltr">{{ $q->vehicle->vin }}</td></tr>@endif
    @if($q->customer->vat_number)<tr><th>الرقم الضريبي للعميل / Customer VATIN</th><td colspan="3">{{ $q->customer->vat_number }}</td></tr>@endif
</table>
<table class="grid">
    <tr><th>#</th><th>الوصف / Description</th><th>خيار / Opt.</th><th class="num">الكمية / Qty</th><th class="num">السعر / Price</th><th class="num">الخصم / Disc.</th><th class="num">الضريبة / VAT</th><th class="num">الإجمالي / Total</th></tr>
    @foreach($q->items as $i => $it)
        <tr><td>{{ $i + 1 }}</td><td>{{ $it->description }} @if($it->part_condition && $it->part_condition !== 'new')<span class="badge">{{ status_label($it->part_condition) }}</span>@endif</td><td>{{ $it->option_group }}</td>
            <td class="num">{{ qty($it->qty) }}</td><td class="num">{{ money($it->unit_price, false) }}</td><td class="num">{{ money($it->discount, false) }}</td><td class="num">{{ money($it->tax_amount, false) }}</td><td class="num">{{ money((float) $it->line_total + (float) $it->tax_amount, false) }}</td></tr>
    @endforeach
</table>
<table class="totals" style="width:50%;margin-{{ is_rtl() ? 'right' : 'left' }}:auto;margin-top:8px">
    <tr><td>المجموع / Subtotal</td><td class="num">{{ money($q->subtotal) }}</td></tr>
    <tr><td>الخصم / Discount</td><td class="num">-{{ money($q->discount_total) }}</td></tr>
    <tr><td>ضريبة القيمة المضافة / VAT</td><td class="num">{{ money($q->tax_total) }}</td></tr>
    <tr class="grand"><td>الإجمالي / Total</td><td class="num">{{ money($q->total) }}</td></tr>
</table>
@if($q->items->whereNotNull('option_group')->isNotEmpty())<p class="muted">* الإجمالي محسوب على الخيار الأول. Total is based on the first repair option.</p>@endif
@if($q->notes)<div class="box" style="margin-top:8px">{!! nl2br(e($q->notes)) !!}</div>@endif
@if($q->terms)<div class="box" style="margin-top:8px"><b>الشروط / Terms:</b><br>{!! nl2br(e($q->terms)) !!}</div>@endif
<table style="margin-top:16px"><tr>
    <td>موافقة العميل / Customer approval<div class="sign"></div></td>
    <td style="text-align:center;width:120px"><img src="{{ \App\Support\Codes::qr(route('public.quotation', $q->access_token), 3) }}" style="width:100px"><div class="muted">للموافقة الإلكترونية<br>Approve online</div></td>
</tr></table>
@endsection
