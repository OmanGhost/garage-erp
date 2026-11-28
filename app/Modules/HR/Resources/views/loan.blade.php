@extends('layouts.app')
@section('title', __('Loan').' #'.$loan->id)
@section('content')
@php
    $paid = \App\Support\Money::sub($loan->total_payable, $loan->remaining);
    $pct = (float) $loan->total_payable > 0 ? round((float) $paid * 100 / (float) $loan->total_payable) : 0;
    $profitLeft = \App\Support\Money::sub($loan->profit_amount, $loan->profit_collected);
    $principalLeft = \App\Support\Money::sub($loan->remaining, $profitLeft);
    $stColor = ['paid' => 'success', 'partial' => 'warning', 'overdue' => 'danger', 'due' => 'secondary'];
    $stLabel = ['paid' => __('Paid'), 'partial' => __('Partly paid'), 'overdue' => __('Overdue'), 'due' => __('Upcoming')];
    $srcLabel = ['payroll' => __('Payroll deduction'), 'manual' => __('Paid by hand'), 'settlement' => __('Early settlement'), 'opening' => __('Opening balance')];
@endphp
<x-page-header :title="($loan->loan_type === 'financing' ? __('Employee financing') : __('Salary advance')).' #'.$loan->id" icon="bi-cash-coin"
    :breadcrumbs="[__('Salary Advances & Loans') => route('crud.index', 'employee-loans'), '#'.$loan->id]">
    <a href="{{ route('vouchers.loan', $loan) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print voucher') }}</a>
</x-page-header>

<div class="row g-3 mb-3">
    @foreach([
        [__('Principal'), money($loan->amount), 'bi-cash', 'primary'],
        [__('Profit'), money($loan->profit_amount).($loan->loan_type === 'financing' ? ' ('.rtrim(rtrim((string) $loan->profit_rate, '0'), '.').'%)' : ''), 'bi-percent', 'info'],
        [__('Total payable'), money($loan->total_payable), 'bi-calculator', 'secondary'],
        [__('Received'), money($paid), 'bi-check2-circle', 'success'],
        [__('Outstanding'), money($loan->remaining), 'bi-hourglass-split', (float) $loan->remaining > 0 ? 'warning' : 'success'],
    ] as [$l, $v, $i, $c])
        <div class="col-6 col-lg"><div class="card h-100"><div class="card-body d-flex align-items-center gap-2">
            <i class="bi {{ $i }} fs-3 text-{{ $c }}"></i><div><div class="small text-body-secondary">{{ $l }}</div><div class="fs-6 fw-bold">{{ $v }}</div></div>
        </div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-calendar3"></i> <b>{{ __('Installment schedule') }}</b>
                <span class="ms-auto small text-body-secondary">{{ $pct }}% {{ __('repaid') }}</span></div>
            <div class="px-3 pt-2"><div class="progress" style="height:8px" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width:{{ $pct }}%"></div></div></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0 mt-2">
                <thead><tr><th>#</th><th>{{ __('Month') }}</th><th class="text-end">{{ __('Installment') }}</th><th class="text-end">{{ __('Received') }}</th>
                    <th class="text-end">{{ __('Left of installment') }}</th><th class="text-end">{{ __('Balance after') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach($schedule as $r)
                    <tr class="{{ $r['status'] === 'overdue' ? 'table-danger' : ($r['status'] === 'paid' ? 'text-body-secondary' : '') }}">
                        <td>{{ $r['n'] }}</td>
                        <td>{{ $r['month']->translatedFormat('F Y') }}</td>
                        <td class="text-end font-monospace">{{ money($r['due'], false) }}</td>
                        <td class="text-end font-monospace text-success">{{ money($r['paid'], false) }}</td>
                        <td class="text-end font-monospace">{{ money(\App\Support\Money::sub($r['due'], $r['paid']), false) }}</td>
                        <td class="text-end font-monospace">{{ money($r['balance'], false) }}</td>
                        <td><span class="badge text-bg-{{ $stColor[$r['status']] }}">{{ $stLabel[$r['status']] }}</span></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="fw-bold"><tr><td colspan="2">{{ __('Total') }}</td><td class="text-end font-monospace">{{ money($loan->total_payable, false) }}</td>
                    <td class="text-end font-monospace text-success">{{ money($paid, false) }}</td><td class="text-end font-monospace">{{ money($loan->remaining, false) }}</td><td colspan="2"></td></tr></tfoot>
            </table></div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-receipt"></i> <b>{{ __('Payments received') }}</b></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Source') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Principal') }}</th><th class="text-end">{{ __('Profit') }}</th><th>{{ __('Notes') }}</th></tr></thead>
                <tbody>
                @forelse($payments as $p)
                    <tr>
                        <td>{{ $p->date->format('Y-m-d') }}</td>
                        <td>{{ $srcLabel[$p->source] ?? $p->source }}@if($p->payroll) · <a href="{{ route('hr.payroll.show', $p->payroll) }}">{{ $p->payroll->period_month?->format('Y-m') }}</a>@endif</td>
                        <td class="text-end font-monospace fw-semibold">{{ money($p->amount, false) }}</td>
                        <td class="text-end font-monospace">{{ money($p->principal, false) }}</td>
                        <td class="text-end font-monospace">{{ money($p->profit, false) }}</td>
                        <td class="small text-body-secondary">{{ $p->notes }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-body-secondary p-3">{{ __('No payments yet. Installments are deducted from the payroll automatically.') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body small">
            <dl class="row mb-0">
                <dt class="col-5">{{ __('Employee') }}</dt><dd class="col-7"><a href="{{ route('crud.show', ['employees', $loan->employee_id]) }}">{{ $loan->employee?->name }}</a></dd>
                <dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $loan->date->format('Y-m-d') }}</dd>
                <dt class="col-5">{{ __('Type') }}</dt><dd class="col-7">{{ $loan->loan_type === 'financing' ? __('Internal financing') : __('Salary advance') }}</dd>
                <dt class="col-5">{{ __('Monthly installment') }}</dt><dd class="col-7 font-monospace">{{ money($loan->installment) }} × {{ $loan->installments_count }}</dd>
                <dt class="col-5">{{ __('First deduction') }}</dt><dd class="col-7">{{ $loan->first_month?->translatedFormat('F Y') ?? '—' }}</dd>
                @if((float) $loan->profit_amount > 0)
                    <dt class="col-5">{{ __('Principal outstanding') }}</dt><dd class="col-7 font-monospace">{{ money($principalLeft) }}</dd>
                    <dt class="col-5">{{ __('Profit outstanding') }}</dt><dd class="col-7 font-monospace">{{ money($profitLeft) }}</dd>
                @endif
                <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7">{!! status_badge($loan->status) !!}</dd>
                @if($loan->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $loan->notes }}</dd>@endif
            </dl>
        </div></div>

        @can('loans.update')
        @if($loan->status === 'active')
            @if($pendingPayroll)
                <div class="alert alert-warning small"><i class="bi bi-hourglass"></i> {{ __('A payroll that is not posted yet already deducts this loan. Post or delete that payroll before recording a payment by hand.') }}</div>
            @else
                <form method="post" action="{{ route('hr.loans.repay', $loan) }}" class="card" x-data="{ settle: false }">@csrf <x-idem/>
                    <div class="card-header fw-semibold"><i class="bi bi-cash-stack"></i> {{ __('Record a payment') }}</div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="settle" name="settle" value="1" x-model="settle">
                            <label class="form-check-label" for="settle">{{ __('Settle the whole balance early (:a)', ['a' => money($loan->remaining)]) }}</label></div>
                        <div class="mb-2" x-show="! settle"><label class="form-label" for="ramt">{{ __('Amount') }}</label>
                            <input id="ramt" type="number" step="0.001" min="0.001" max="{{ $loan->remaining }}" name="amount" class="form-control" :required="! settle" value="{{ (float) $loan->installment <= (float) $loan->remaining ? $loan->installment : $loan->remaining }}"></div>
                        <div class="mb-2"><label class="form-label" for="rbank">{{ __('Received into') }}</label>
                            <select id="rbank" name="bank_account_id" class="form-select">@foreach($banks as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
                        <input name="notes" class="form-control" placeholder="{{ __('Notes') }}" maxlength="250" aria-label="{{ __('Notes') }}">
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-success">{{ __('Save payment') }}</button></div>
                </form>
            @endif
        @endif
        @endcan
    </div>
</div>
@endsection
