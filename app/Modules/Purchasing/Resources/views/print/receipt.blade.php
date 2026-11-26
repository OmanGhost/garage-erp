@extends('layouts.print')
@section('title', $grn->number)
@section('pdf-url', route('goods-receipts.print', [$grn, 'pdf']))
@section('styles')
    .signs td { width: 33%; text-align: center; vertical-align: bottom; padding: 0 8px; }
@endsection
@section('content')
<div class="doc-title">سند استلام بضاعة <span class="bilingual">Goods Received Note (GRN)</span></div>
<table class="grid" style="margin-bottom:8px">
    <tr><th>رقم السند / GRN No.</th><td><b>{{ $grn->number }}</b></td><th>تاريخ الاستلام / Received</th><td>{{ $grn->received_at?->format('Y-m-d H:i') }}</td></tr>
    <tr><th>المورد / Supplier</th><td>{{ $grn->supplier->name }}@if($grn->supplier->phone) · <span dir="ltr">{{ $grn->supplier->phone }}</span>@endif</td><th>أمر الشراء / PO</th><td>{{ $grn->order?->number ?? '—' }}</td></tr>
    <tr><th>المستودع / Warehouse</th><td>{{ $grn->warehouse->displayName() }}</td><th>مرجع المورد / Supplier ref.</th><td>{{ $grn->supplier_reference ?: '—' }}</td></tr>
    <tr><th>الحالة / Status</th><td>{{ status_label($grn->status) }}</td><th>الرقم الضريبي للمورد / VATIN</th><td>{{ $grn->supplier->vat_number ?: '—' }}</td></tr>
</table>
<table class="grid">
    <tr><th>#</th><th>الصنف / Item</th><th>رقم القطعة / Part no.</th><th class="num">الكمية<br>Qty</th><th class="num">المرتجع<br>Returned</th><th>الموقع / دفعة<br>Location / batch</th><th class="num">التكلفة<br>Unit cost</th><th class="num">الإجمالي<br>Total</th></tr>
    @foreach($grn->items as $i => $it)
        <tr><td>{{ $i + 1 }}</td><td>{{ $it->part->displayName() }}</td><td dir="ltr">{{ $it->part->oem_number ?: $it->part->sku }}</td>
            <td class="num">{{ qty($it->qty) }}</td><td class="num">{{ (float) $it->returned_qty > 0 ? qty($it->returned_qty) : '—' }}</td>
            <td>{{ $it->location?->code ?? '' }} {{ $it->batch_number }}@if($it->expiry_date) · {{ \Illuminate\Support\Carbon::parse($it->expiry_date)->format('Y-m-d') }}@endif</td>
            <td class="num">{{ money($it->unit_cost, false) }}</td><td class="num">{{ money($it->line_total, false) }}</td></tr>
        @if($it->serial_numbers)<tr><td></td><td colspan="7" class="muted" dir="ltr">S/N: {{ $it->serial_numbers }}</td></tr>@endif
    @endforeach
    <tr><th colspan="3" style="text-align:{{ is_rtl() ? 'left' : 'right' }}">الإجمالي / Total</th><th class="num">{{ qty($grn->items->sum('qty')) }}</th><th colspan="3"></th><th class="num">{{ money($grn->total) }}</th></tr>
</table>
@if($grn->notes)<p class="muted" style="margin-top:6px">ملاحظات / Notes: {{ $grn->notes }}</p>@endif
<p class="muted" style="margin-top:8px">
    تم استلام الأصناف أعلاه وفحصها وإدخالها إلى المستودع بالكميات الموضحة.<br>
    The items above were received, checked and put into stock in the quantities shown.
</p>
<table class="signs" style="margin-top:24px"><tr>
    <td><div class="sign"></div>أمين المستودع / Storekeeper<br><span class="muted">{{ $grn->creator?->name }}</span></td>
    <td><div class="sign"></div>اعتمده / Approved by<br><span class="muted">{{ $grn->poster?->name }}</span></td>
    <td><div class="sign"></div>مندوب المورد / Supplier representative</td>
</tr></table>
@endsection
