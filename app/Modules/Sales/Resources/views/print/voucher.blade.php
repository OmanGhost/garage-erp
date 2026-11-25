@extends('layouts.print')
@section('title', $p->number)
@section('content')
<div class="doc-title">{{ $p->direction === 'in' ? 'سند قبض / Receipt Voucher' : 'سند صرف / Payment Voucher' }}</div>
<table class="grid">
    <tr><th>رقم السند / No.</th><td>{{ $p->number }}</td><th>التاريخ / Date</th><td>{{ $p->date->format('Y-m-d') }}</td></tr>
    <tr><th>{{ $p->direction === 'in' ? 'استلمنا من / Received from' : 'صرفنا إلى / Paid to' }}</th><td colspan="3">{{ $p->party?->name }}</td></tr>
    <tr><th>المبلغ / Amount</th><td colspan="3" style="font-size:16px"><b>{{ money($p->amount) }}</b></td></tr>
    <tr><th>طريقة الدفع / Method</th><td>{{ status_label($p->method) }}</td><th>الحساب / Account</th><td>{{ $p->bankAccount->name }}</td></tr>
    <tr><th>المرجع / Reference</th><td colspan="3">{{ $p->reference }} {{ $p->cheque_number ? 'Cheque '.$p->cheque_number.' '.$p->cheque_date?->format('Y-m-d') : '' }}</td></tr>
    @if($p->allocations->isNotEmpty())<tr><th>عن / For</th><td colspan="3">@foreach($p->allocations as $a){{ $a->allocatable?->number }} ({{ money($a->amount, false) }}) @endforeach</td></tr>@endif
    @if($p->notes)<tr><th>ملاحظات / Notes</th><td colspan="3">{{ $p->notes }}</td></tr>@endif
</table>
<table style="margin-top:30px"><tr><td>المحاسب / Accountant<div class="sign"></div></td><td>المستلم / Receiver<div class="sign"></div></td></tr></table>
@endsection
