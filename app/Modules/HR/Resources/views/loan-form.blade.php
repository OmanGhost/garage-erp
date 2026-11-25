@extends('layouts.app')
@section('title', __('Salary advance'))
@section('content')
<x-page-header :title="__('New salary advance / loan')" icon="bi-cash" :breadcrumbs="[__('Advances & Loans') => route('crud.index', 'employee-loans'), __('New')]"/>
<form method="post" action="{{ route('hr.loans.store') }}" class="card" style="max-width:640px">@csrf <x-idem/><div class="card-body row g-3">
    <div class="col-12"><label class="form-label required">{{ __('Employee') }}</label><x-lookup name="employee_id" type="employees" required/></div>
    <div class="col-md-6"><label class="form-label required">{{ __('Amount') }}</label><input type="number" step="0.001" min="0.001" name="amount" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label required">{{ __('Monthly installment') }}</label><input type="number" step="0.001" min="0.001" name="installment" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">{{ __('Paid from') }}</label><select name="bank_account_id" class="form-select">@foreach($banks as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
</div><div class="card-footer text-end"><button class="btn btn-primary">{{ __('Save & pay') }}</button></div></form>
@endsection
