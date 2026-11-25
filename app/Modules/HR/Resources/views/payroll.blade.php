@extends('layouts.app')
@section('title', $p->number)
@section('content')
<x-page-header :title="__('Payroll').' '.$p->period_month->format('Y-m')" icon="bi-cash-coin" :breadcrumbs="[__('Payroll') => route('crud.index', 'payrolls'), $p->number]" :subtitle="status_badge($p->status)">
    @can('payroll.approve')
        @if($p->status === 'draft')<form method="post" action="{{ route('hr.payroll.action', [$p, 'approve']) }}">@csrf <x-idem/><button class="btn btn-primary">{{ __('Approve') }}</button></form>@endif
        @if($p->status === 'approved')<form method="post" action="{{ route('hr.payroll.action', [$p, 'post']) }}" data-confirm="{{ __('Post payroll to accounts?') }}">@csrf <x-idem/><button class="btn btn-success">{{ __('Post to accounts') }}</button></form>@endif
        @if($p->status === 'paid')<a href="{{ route('vouchers.payroll', $p) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print voucher') }}</a>@endif
        @if($p->status === 'posted')<form method="post" action="{{ route('hr.payroll.action', [$p, 'pay']) }}" class="d-flex gap-1">@csrf <x-idem/><select name="bank_account_id" class="form-select">@foreach($banks as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select><button class="btn btn-success text-nowrap">{{ __('Pay salaries') }}</button></form>@endif
    @endcan
    @if($p->status === 'draft')<a href="{{ route('hr.payroll.create') }}" class="btn btn-outline-secondary">{{ __('Recalculate') }}</a>@endif
</x-page-header>
<div class="card"><div class="table-responsive"><table class="table table-sm table-erp mb-0">
    <thead><tr><th>{{ __('Employee') }}</th><th class="text-end">{{ __('Basic') }}</th><th class="text-end">{{ __('Allowances') }}</th><th class="text-end">{{ __('Overtime') }}</th><th class="text-end">{{ __('Commissions') }}</th><th class="text-end">{{ __('Bonuses') }}</th><th class="text-end">{{ __('Deductions') }}</th><th class="text-end">{{ __('Absence') }}</th><th class="text-end">{{ __('Loan') }}</th><th class="text-end">{{ __('Net') }}</th><th></th></tr></thead>
    <tbody>@foreach($p->items as $i)<tr><td>{{ $i->employee->name }}<div class="small text-body-secondary">{{ __('Days') }} {{ (float) $i->worked_days }} · OT {{ round($i->overtime_minutes / 60, 1) }}h</div></td>
        @foreach(['basic', 'allowances', 'overtime', 'commissions', 'bonuses', 'deductions', 'absence_deduction', 'loan_deduction'] as $k)<td class="text-end font-monospace">{{ money($i->{$k}, false) }}</td>@endforeach
        <td class="text-end font-monospace fw-bold">{{ money($i->net, false) }}</td><td><a href="{{ route('hr.payroll.payslip', [$p, $i]) }}" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-printer"></i></a></td></tr>@endforeach</tbody>
    <tfoot><tr class="fw-bold"><td>{{ __('Total') }}</td><td colspan="5" class="text-end">{{ __('Gross') }} {{ money($p->total_gross) }}</td><td colspan="3" class="text-end">{{ __('Deductions') }} {{ money($p->total_deductions) }}</td><td class="text-end font-monospace">{{ money($p->total_net) }}</td><td></td></tr></tfoot>
</table></div></div>
@endsection
