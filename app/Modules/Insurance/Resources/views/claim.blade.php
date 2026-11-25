@extends('layouts.app')
@section('title', $c->number)
@section('content')
<x-page-header :title="__('Insurance claim').' '.$c->number" icon="bi-shield-check" :breadcrumbs="[__('Insurance Claims') => route('crud.index', 'insurance-claims'), $c->number]"
    :subtitle="e($c->insuranceCompany->name).' · '.e($c->customer->name).' · '.e($c->vehicle->label()).' · '.status_badge($c->status)">
    @can('insurance_claims.update')<a href="{{ route('crud.edit', ['insurance-claims', $c->id]) }}" class="btn btn-outline-primary">{{ __('Edit') }}</a>@endcan
    @if(in_array($c->status, ['approved', 'partially_approved']) && $c->jobCard)@can('invoices.create')<form method="post" action="{{ route('insurance.claims.invoice', $c) }}">@csrf <x-idem/><button class="btn btn-success"><i class="bi bi-receipt"></i> {{ __('Invoice insurer & customer') }}</button></form>@endcan @endif
</x-page-header>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-body">
            <div class="row text-center g-2">
                @foreach([[__('Estimated'), $c->estimated_cost], [__('Approved by insurer'), $c->approved_amount], [__('Deductible (customer)'), $c->deductible_amount]] as [$l, $v])
                    <div class="col-4"><div class="border rounded p-2"><div class="small text-body-secondary">{{ $l }}</div><div class="fw-bold font-monospace">{{ money($v) }}</div></div></div>
                @endforeach
            </div>
            <dl class="row small mt-3 mb-0">
                <dt class="col-4">{{ __('Insurer claim no.') }}</dt><dd class="col-8">{{ $c->claim_number ?? '—' }}</dd>
                <dt class="col-4">{{ __('Policy') }}</dt><dd class="col-8">{{ $c->policy_number }}</dd>
                <dt class="col-4">{{ __('Accident') }}</dt><dd class="col-8">{{ $c->accident_date?->format('Y-m-d') }} · {{ $c->accident_location }}</dd>
                <dt class="col-4">{{ __('Police report') }}</dt><dd class="col-8">{{ $c->police_report_number }}</dd>
                <dt class="col-4">{{ __('Surveyor') }}</dt><dd class="col-8">{{ $c->surveyor_name }}</dd>
                <dt class="col-4">{{ __('Job card') }}</dt><dd class="col-8">@if($c->jobCard)<a href="{{ route('job-cards.show', $c->jobCard) }}">{{ $c->jobCard->number }}</a>@else — @endif</dd>
                <dt class="col-4">{{ __('Description') }}</dt><dd class="col-8">{{ $c->accident_description }}</dd>
            </dl>
        </div></div>
        <x-attachments :model="$c" :title="__('Accident reports, photos & approvals')"/>
    </div>
    <div class="col-lg-5">
        @can('insurance_claims.update')
        <form method="post" action="{{ route('insurance.claims.status', $c) }}" class="card mb-3">@csrf <x-idem/>
            <div class="card-header">{{ __('Update claim status') }}</div>
            <div class="card-body row g-2">
                <div class="col-12"><select name="status" class="form-select">@foreach(\App\Modules\Insurance\Services\InsuranceService::STATUSES as $s)<option value="{{ $s }}" @selected($c->status === $s)>{{ status_label($s) }}</option>@endforeach</select></div>
                <div class="col-6"><input type="number" step="0.001" name="approved_amount" value="{{ (float) $c->approved_amount }}" class="form-control" placeholder="{{ __('Approved amount') }}"></div>
                <div class="col-6"><input type="number" step="0.001" name="deductible_amount" value="{{ (float) $c->deductible_amount }}" class="form-control" placeholder="{{ __('Deductible') }}"></div>
                <div class="col-12"><input name="claim_number" value="{{ $c->claim_number }}" class="form-control" placeholder="{{ __('Insurer claim no.') }}"></div>
                <div class="col-12"><textarea name="notes" class="form-control" rows="2">{{ $c->notes }}</textarea></div>
                <div class="col-12"><button class="btn btn-primary w-100">{{ __('Save') }}</button></div>
            </div>
        </form>
        @endcan
        <div class="card"><div class="card-header">{{ __('Invoices') }}</div><ul class="list-group list-group-flush small">
            @forelse($c->invoices as $i)<li class="list-group-item d-flex"><a href="{{ route('invoices.show', $i) }}">{{ $i->displayNumber() }}</a><span class="ms-2">{{ $i->customer?->name }}</span><span class="ms-auto me-2 font-monospace">{{ money($i->total, false) }}</span>{!! status_badge($i->status) !!}</li>
            @empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul></div>
    </div>
</div>
@endsection
