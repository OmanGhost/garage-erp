@extends('layouts.app')
@section('title', __('Role change request').' '.$r->number)
@section('content')
<x-page-header :title="__('Role change request').' '.$r->number" icon="bi-person-up" :breadcrumbs="[__('Role change requests') => route('role-requests.index'), $r->number]">
    <a href="{{ route('role-requests.print', $r) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print') }}</a>
</x-page-header>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                <span class="badge text-bg-light border fs-6">{{ $r->current_role ? __($r->current_role) : '—' }}</span>
                <i class="bi {{ is_rtl() ? 'bi-arrow-left' : 'bi-arrow-right' }} fs-4 text-primary"></i>
                <span class="badge text-bg-primary fs-6">{{ __($r->requested_role) }}</span>
                <span class="ms-auto">{!! status_badge($r->status) !!}</span>
            </div>
            <dl class="row small mb-0">
                <dt class="col-4">{{ __('User') }}</dt><dd class="col-8">{{ $r->user?->name }} <span class="text-body-secondary">{{ $r->user?->email }}</span></dd>
                <dt class="col-4">{{ __('Branch') }}</dt><dd class="col-8">{{ $r->branch?->displayName() ?? '—' }}</dd>
                <dt class="col-4">{{ __('Requested by') }}</dt><dd class="col-8">{{ $r->requester?->name }} · {{ $r->created_at->format('Y-m-d H:i') }}</dd>
                @if($r->effective_date)<dt class="col-4">{{ __('Effective date') }}</dt><dd class="col-8">{{ $r->effective_date->format('Y-m-d') }}</dd>@endif
                <dt class="col-4">{{ __('Reason') }}</dt><dd class="col-8" style="white-space:pre-wrap">{{ $r->reason }}</dd>
                @if($r->decided_at)
                    <dt class="col-4">{{ __('Decision') }}</dt><dd class="col-8">{!! status_badge($r->status) !!} {{ $r->decider?->name }} · {{ $r->decided_at->format('Y-m-d H:i') }}</dd>
                    @if($r->decision_notes)<dt class="col-4">{{ __('Notes') }}</dt><dd class="col-8" style="white-space:pre-wrap">{{ $r->decision_notes }}</dd>@endif
                @endif
            </dl>
        </div></div>
    </div>
    <div class="col-lg-5">
        @if($r->status === 'pending')
            @if(! $denyReason)
                <form method="post" action="{{ route('role-requests.decide', $r) }}" class="card mb-3" x-data="{ d: '' }">@csrf
                    <div class="card-header fw-semibold"><i class="bi bi-check2-square"></i> {{ __('Decision') }}</div>
                    <div class="card-body">
                        <div class="d-flex gap-3 mb-2">
                            <div class="form-check"><input class="form-check-input" type="radio" name="decision" value="approve" id="dA" x-model="d" required><label class="form-check-label text-success fw-semibold" for="dA">{{ __('Approve') }}</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="decision" value="reject" id="dR" x-model="d"><label class="form-check-label text-danger fw-semibold" for="dR">{{ __('Reject') }}</label></div>
                        </div>
                        <textarea name="decision_notes" rows="3" class="form-control" maxlength="2000" :required="d === 'reject'" :placeholder="d === 'reject' ? @js(__('Reason for the rejection (required)')) : @js(__('Notes (optional)'))" aria-label="{{ __('Notes') }}"></textarea>
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary" :class="d === 'reject' ? 'btn-danger' : (d === 'approve' ? 'btn-success' : '')">{{ __('Save decision') }}</button></div>
                </form>
            @else
                <div class="alert alert-secondary small"><i class="bi bi-lock"></i> {{ $denyReason }}</div>
                @if($approvers->isNotEmpty())
                    <div class="card mb-3"><div class="card-body small"><div class="fw-semibold mb-1">{{ __('Can be decided by') }}</div>
                        @foreach($approvers as $a)<span class="badge text-bg-light border me-1">{{ $a->name }}</span>@endforeach</div></div>
                @endif
            @endif
            @if((int) $r->requested_by === (int) auth()->id())
                <form method="post" action="{{ route('role-requests.cancel', $r) }}" data-confirm="{{ __('Cancel this request?') }}">@csrf
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-x-lg"></i> {{ __('Cancel request') }}</button></form>
            @endif
        @endif
    </div>
</div>
@endsection
