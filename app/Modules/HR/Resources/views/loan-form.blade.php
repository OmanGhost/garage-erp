@extends('layouts.app')
@section('title', __('Salary advance'))
@section('content')
@php
    $locale = app()->getLocale();
    $monthNames = collect(range(0, 23))->mapWithKeys(fn ($i) => [now()->startOfMonth()->addMonthsNoOverflow($i)->format('Y-m') => now()->startOfMonth()->addMonthsNoOverflow($i)->translatedFormat('F Y')])->all();
@endphp
<x-page-header :title="__('New salary advance / loan')" icon="bi-cash" :breadcrumbs="[__('Advances & Loans') => route('crud.index', 'employee-loans'), __('New')]"/>
<form method="post" action="{{ route('hr.loans.store') }}" class="row g-3"
      x-data="{ type: '{{ old('loan_type', 'advance') }}', amount: '{{ old('amount') }}', rate: '{{ old('profit_rate', $defaultRate) }}', months: '{{ old('installments_count', 12) }}',
                installment: '{{ old('installment') }}', first: '{{ old('first_month', now()->addMonthNoOverflow()->format('Y-m')) }}', names: @js($monthNames),
                get a() { return Math.max(0, parseFloat(this.amount) || 0) },
                get profit() { return this.type === 'financing' ? Math.round(this.a * (parseFloat(this.rate) || 0) * 10) / 1000 : 0 },
                get total() { return Math.round((this.a + this.profit) * 1000) / 1000 },
                get inst() { if (this.type === 'financing') { const m = Math.max(1, parseInt(this.months) || 1); return Math.ceil(Math.round(this.total * 1000 / m * 1e6) / 1e6) / 1000 }
                             return Math.min(this.total, Math.max(0, parseFloat(this.installment) || 0)) },
                get rows() { const out = []; let left = this.total; let [y, m] = this.first.split('-').map(Number); if (! this.inst) return out;
                             for (let n = 1; left > 0.0005 && n <= 120; n++) { const due = Math.min(this.inst, left); left = Math.round((left - due) * 1000) / 1000;
                                 const key = y + '-' + String(m).padStart(2, '0'); out.push({ n, month: this.names[key] || key, due, left }); m++; if (m > 12) { m = 1; y++; } }
                             return out },
                f(v) { return Number(v).toFixed(3) } }">@csrf <x-idem/>
    <div class="col-lg-6">
        <div class="card"><div class="card-body row g-3">
            <div class="col-12"><label class="form-label required">{{ __('Employee') }}</label><x-lookup name="employee_id" type="employees" required/></div>
            <div class="col-12">
                <label class="form-label">{{ __('Type') }}</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="loan_type" id="tAdv" value="advance" x-model="type"><label class="btn btn-outline-primary" for="tAdv"><i class="bi bi-cash"></i> {{ __('Salary advance') }}</label>
                    <input type="radio" class="btn-check" name="loan_type" id="tFin" value="financing" x-model="type"><label class="btn btn-outline-primary" for="tFin"><i class="bi bi-percent"></i> {{ __('Internal financing') }}</label>
                </div>
                <div class="form-text" x-show="type === 'financing'">{{ __('A profit percentage is added to the amount; the total is repaid in equal monthly installments deducted from the salary.') }}</div>
            </div>
            <div class="col-md-6"><label class="form-label required" for="amt">{{ __('Amount') }}</label><input id="amt" type="number" step="0.001" min="0.001" name="amount" class="form-control" required x-model="amount"></div>
            <div class="col-md-6" x-show="type === 'financing'"><label class="form-label required" for="rate">{{ __('Profit rate (%)') }}</label>
                <input id="rate" type="number" step="0.01" min="0" max="100" name="profit_rate" class="form-control" x-model="rate" :required="type === 'financing'" :disabled="type !== 'financing'"></div>
            <div class="col-md-6" x-show="type === 'financing'"><label class="form-label required" for="months">{{ __('Number of installments (months)') }}</label>
                <input id="months" type="number" step="1" min="1" max="120" name="installments_count" class="form-control" x-model="months" :required="type === 'financing'" :disabled="type !== 'financing'"></div>
            <div class="col-md-6" x-show="type === 'advance'"><label class="form-label required" for="inst">{{ __('Monthly installment') }}</label>
                <input id="inst" type="number" step="0.001" min="0.001" name="installment" class="form-control" x-model="installment" :required="type === 'advance'" :disabled="type !== 'advance'"></div>
            <div class="col-md-6"><label class="form-label" for="first">{{ __('First deduction month') }}</label>
                <select id="first" name="first_month" class="form-select" x-model="first">@foreach($monthNames as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="bank">{{ __('Paid from') }}</label><select id="bank" name="bank_account_id" class="form-select">@foreach($banks as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label" for="notes">{{ __('Notes') }}</label><input id="notes" name="notes" class="form-control" maxlength="250" value="{{ old('notes') }}"></div>
        </div>
        <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-check2"></i> {{ __('Save & pay') }}</button></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-calendar3"></i> {{ __('Repayment plan') }}</div>
            <div class="card-body">
                <div class="row g-2 text-center mb-3">
                    <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="small text-body-secondary">{{ __('Principal') }}</div><div class="fw-bold font-monospace" x-text="f(a)"></div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="small text-body-secondary">{{ __('Profit') }}</div><div class="fw-bold font-monospace text-info" x-text="f(profit)"></div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="small text-body-secondary">{{ __('Total payable') }}</div><div class="fw-bold font-monospace" x-text="f(total)"></div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-2 border-primary"><div class="small text-body-secondary">{{ __('Monthly installment') }}</div><div class="fw-bold font-monospace text-primary" x-text="f(inst)"></div></div></div>
                </div>
                <div class="table-responsive" style="max-height:420px">
                    <table class="table table-sm mb-0">
                        <thead class="sticky-top bg-body"><tr><th>#</th><th>{{ __('Month') }}</th><th class="text-end">{{ __('Installment') }}</th><th class="text-end">{{ __('Balance after') }}</th></tr></thead>
                        <tbody>
                            <template x-for="r in rows" :key="r.n"><tr><td x-text="r.n"></td><td x-text="r.month"></td><td class="text-end font-monospace" x-text="f(r.due)"></td><td class="text-end font-monospace" x-text="f(r.left)"></td></tr></template>
                            <tr x-show="! rows.length"><td colspan="4" class="text-body-secondary">{{ __('Enter the amount and the installment to see the plan.') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="form-text mt-2">{{ __('The final figures are calculated again by the system when saving.') }}</div>
            </div>
        </div>
    </div>
</form>
@endsection
