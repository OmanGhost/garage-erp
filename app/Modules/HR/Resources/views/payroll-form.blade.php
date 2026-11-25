@extends('layouts.app')
@section('title', __('Calculate payroll'))
@section('content')
<x-page-header :title="__('Calculate monthly payroll')" icon="bi-cash-coin" :breadcrumbs="[__('Payroll') => route('crud.index', 'payrolls'), __('New')]"/>
<form method="post" action="{{ route('hr.payroll.store') }}" class="card" style="max-width:560px">@csrf <x-idem/><div class="card-body row g-3">
    <div class="col-md-6"><label class="form-label">{{ __('Month') }}</label><input type="month" name="month" value="{{ now()->subMonth()->format('Y-m') }}" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">{{ __('Branch') }}</label><select name="branch_id" class="form-select"><option value="">{{ __('All branches') }}</option>@foreach($branches as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
    <div class="col-12 small text-body-secondary">{{ __('Includes basic salary, allowances, overtime from attendance, pending commissions, bonuses, deductions, unpaid absence and loan installments.') }}</div>
</div><div class="card-footer text-end"><button class="btn btn-primary">{{ __('Calculate') }}</button></div></form>
@endsection
