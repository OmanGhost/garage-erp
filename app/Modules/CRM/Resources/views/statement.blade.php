@extends('layouts.print')
@section('title', __('Account statement'))
@section('pdf-url', request()->fullUrlWithQuery(['format' => 'pdf']))
@section('content')
<div class="doc-title">كشف حساب <span class="bilingual">Statement of Account</span></div>
<table class="grid" style="margin-bottom:8px">
    <tr><th>{{ $type === 'customer' ? 'العميل / Customer' : 'المورد / Supplier' }}</th><td>{{ $party->name }} ({{ $party->code }})</td><th>الفترة / Period</th><td>{{ $from }} → {{ $to }}</td></tr>
    <tr><th>الهاتف / Phone</th><td dir="ltr">{{ $party->phone }}</td><th>الرقم الضريبي / VATIN</th><td>{{ $party->vat_number ?? '—' }}</td></tr>
</table>
<table class="grid">
    <tr><th>التاريخ / Date</th><th>المستند / Document</th><th>البيان / Description</th><th class="num">مدين / Debit</th><th class="num">دائن / Credit</th><th class="num">الرصيد / Balance</th></tr>
    <tr><td colspan="5"><b>رصيد افتتاحي / Opening balance</b></td><td class="num"><b>{{ money($st['opening'], false) }}</b></td></tr>
    @foreach($st['rows'] as $r)
        <tr><td>{{ $r->date }}</td><td>{{ $r->number }}</td><td>{{ $r->line_description ?: $r->description }}</td><td class="num">{{ (float) $r->debit ? money($r->debit, false) : '' }}</td><td class="num">{{ (float) $r->credit ? money($r->credit, false) : '' }}</td><td class="num">{{ money($r->balance, false) }}</td></tr>
    @endforeach
    <tr><td colspan="5"><b>الرصيد الختامي / Closing balance</b></td><td class="num"><b>{{ money($st['closing']) }}</b></td></tr>
</table>
<p class="muted">{{ $type === 'customer' ? 'رصيد موجب = مستحق على العميل / Positive = amount due from customer' : 'رصيد سالب = مستحق للمورد / Negative = amount due to supplier' }}</p>
@endsection
