@extends('layouts.app')
@section('title', __('Role change request'))
@section('content')
@php
    $current = $users->mapWithKeys(fn ($u) => [$u->id => ['role' => $u->roles->pluck('name')->first(), 'label' => __($u->roles->pluck('name')->first() ?? '—')]])->all();
@endphp
<x-page-header :title="__('New role change request')" icon="bi-person-up" :breadcrumbs="[__('Role change requests') => route('role-requests.index'), __('New')]"/>
<form method="post" action="{{ route('role-requests.store') }}" class="card" style="max-width:720px"
      x-data="{ user: '{{ old('user_id', $selected ?: '') }}', current: @js($current), get cur() { return this.current[this.user] || null } }">@csrf
    <div class="card-body row g-3">
        <div class="col-12"><label class="form-label required" for="rqUser">{{ __('User') }}</label>
            <select id="rqUser" name="user_id" class="form-select" x-model="user" required>
                <option value="">—</option>
                @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }} — {{ $u->email }}</option>@endforeach
            </select></div>
        <div class="col-md-6"><label class="form-label">{{ __('Current role') }}</label>
            <div class="form-control bg-body-tertiary" x-text="cur ? cur.label : '—'"></div></div>
        <div class="col-md-6"><label class="form-label required" for="rqRole">{{ __('Requested role') }}</label>
            <select id="rqRole" name="requested_role" class="form-select" required>
                <option value="">—</option>
                @foreach($roles as $role)<option value="{{ $role }}" @selected(old('requested_role') === $role) x-bind:disabled="cur && cur.role === @js($role)">{{ __($role) }}</option>@endforeach
            </select>
            <div class="form-text">{{ __('Company Admin and General Manager can only be granted by a company administrator.') }}</div></div>
        <div class="col-12"><label class="form-label required" for="rqReason">{{ __('Reason for the change') }}</label>
            <textarea id="rqReason" name="reason" rows="4" class="form-control" required minlength="10" maxlength="2000" placeholder="{{ __('e.g. moved from accounts to the cash counter of the Sohar branch') }}">{{ old('reason') }}</textarea></div>
        <div class="col-md-6"><label class="form-label" for="rqDate">{{ __('Effective date') }}</label>
            <input id="rqDate" type="date" name="effective_date" class="form-control" value="{{ old('effective_date') }}"></div>
        <div class="col-12"><div class="alert alert-info small mb-0"><i class="bi bi-info-circle"></i> {{ __('The role changes only after a branch manager or higher approves the request. You will be notified of the decision.') }}</div></div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-send"></i> {{ __('Submit for approval') }}</button></div>
</form>
@endsection
