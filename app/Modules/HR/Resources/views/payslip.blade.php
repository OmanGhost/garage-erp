@extends('layouts.print')
@section('title', __('Payslip'))
@section('content')
<div class="doc-title">قسيمة راتب <span class="bilingual">Payslip — {{ $p->period_month->format('Y-m') }}</span></div>
<table class="grid" style="margin-bottom:8px">
    <tr><th>الموظف / Employee</th><td>{{ $i->employee->name }} ({{ $i->employee->code }})</td><th>المسمى / Title</th><td>{{ $i->employee->jobTitle?->name }}</td></tr>
    <tr><th>القسم / Dept.</th><td>{{ $i->employee->department?->name }}</td><th>IBAN</th><td dir="ltr">{{ $i->employee->iban }}</td></tr>
</table>
<table class="grid">
    <tr><th>الاستحقاقات / Earnings</th><th class="num"></th><th>الاستقطاعات / Deductions</th><th class="num"></th></tr>
    <tr><td>الراتب الأساسي / Basic</td><td class="num">{{ money($i->basic, false) }}</td><td>خصومات / Deductions</td><td class="num">{{ money($i->deductions, false) }}</td></tr>
    <tr><td>البدلات / Allowances</td><td class="num">{{ money($i->allowances, false) }}</td><td>غياب / Absence</td><td class="num">{{ money($i->absence_deduction, false) }}</td></tr>
    <tr><td>عمل إضافي / Overtime</td><td class="num">{{ money($i->overtime, false) }}</td><td>قسط سلفة / Loan</td><td class="num">{{ money($i->loan_deduction, false) }}</td></tr>
    <tr><td>عمولات / Commissions</td><td class="num">{{ money($i->commissions, false) }}</td><td></td><td></td></tr>
    <tr><td>مكافآت / Bonuses</td><td class="num">{{ money($i->bonuses, false) }}</td><td></td><td></td></tr>
    <tr><th>الإجمالي / Gross</th><th class="num">{{ money($i->gross, false) }}</th><th>الإجمالي / Total</th><th class="num">{{ money((float) $i->deductions + (float) $i->absence_deduction + (float) $i->loan_deduction, false) }}</th></tr>
</table>
<div class="doc-title" style="margin-top:12px">صافي الراتب / Net pay: {{ money($i->net) }}</div>
<table style="margin-top:20px"><tr><td>توقيع الموظف / Employee<div class="sign"></div></td><td>الموارد البشرية / HR<div class="sign"></div></td></tr></table>
@endsection
