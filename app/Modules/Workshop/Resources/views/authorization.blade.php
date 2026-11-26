@extends('layouts.app')
@section('title', $a->number)
@section('content')
@php
    $st = $a->effectiveStatus();
    $colors = ['pending' => 'warning', 'active' => 'success', 'used' => 'secondary', 'revoked' => 'danger', 'expired' => 'secondary', 'not_started' => 'info'];
    $steps = [
        ['done' => true, 'label' => __('Authorization issued'), 'detail' => $a->created_at?->format('Y-m-d H:i').' · '.($a->creator?->name ?? '—')],
        ['done' => (bool) $a->approved_at, 'label' => __('Approved by the owner'), 'detail' => $a->approved_at ? ($a->approval_method === 'code' ? __('verification code') : __('signed letter')).' · '.$a->approved_at->format('Y-m-d H:i') : __('Waiting for the owner')],
        ['done' => $a->uses_count > 0, 'label' => __('Used'), 'detail' => $a->uses_count ? trans_choice('{1} once|[2,*] :n times', $a->uses_count, ['n' => $a->uses_count]).' · '.$a->last_used_at?->format('Y-m-d H:i') : '—'],
    ];
@endphp
<x-page-header :title="__('Authorization').' '.$a->number" icon="bi-person-badge" :breadcrumbs="[__('Handover Authorizations') => route('handover-authorizations.index'), $a->number]"
    :subtitle="e($a->person_name).' · '.e(status_label($a->type)).' · <span class=\'badge text-bg-'.($colors[$st] ?? 'secondary').'\'>'.e(status_label($st)).'</span>'">
    @can('handover_authorizations.print')
        <a class="btn btn-outline-secondary" href="{{ route('handover-authorizations.print', $a) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Print authorization letter') }}</a>
    @endcan
</x-page-header>

@if($st === 'pending')
    <div class="alert alert-warning d-flex gap-2"><i class="bi bi-hourglass-split fs-5"></i><div>{{ __('This authorization cannot be used yet: the owner must approve it.') }}</div></div>
@elseif(in_array($st, ['expired', 'revoked', 'used'], true))
    <div class="alert alert-secondary d-flex gap-2"><i class="bi bi-slash-circle fs-5"></i><div>{{ __('This authorization can no longer be used (:s).', ['s' => status_label($st)]) }} @if($a->revoke_reason)— {{ $a->revoke_reason }}@endif</div></div>
@endif

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex flex-wrap gap-4">
                @foreach($steps as $i => $s)
                    <div class="d-flex gap-2 align-items-start"><i class="bi {{ $s['done'] ? 'bi-check-circle-fill text-success' : 'bi-circle text-body-tertiary' }} fs-5"></i>
                        <div><div class="fw-semibold">{{ $i + 1 }}. {{ $s['label'] }}</div><div class="small text-body-secondary">{{ $s['detail'] }}</div></div></div>
                @endforeach
            </div>
        </div></div>
        <div class="row g-3 mb-3">
            <div class="col-md-6"><div class="card h-100"><div class="card-header"><i class="bi bi-person-vcard"></i> {{ __('Authorized person') }}</div><div class="card-body small">
                <div class="fs-5 fw-semibold">{{ $a->person_name }}</div>
                <div>{{ $a->id_type ? status_label($a->id_type) : __('ID') }}: <bdi dir="ltr" class="font-monospace">{{ $a->id_number }}</bdi></div>
                <div>{{ __('Phone') }}: <bdi dir="ltr">{{ $a->person_phone ?: '—' }}</bdi></div>
                <div>{{ __('Relationship') }}: {{ $a->relationship ? status_label($a->relationship) : '—' }}</div>
            </div></div></div>
            <div class="col-md-6"><div class="card h-100"><div class="card-header"><i class="bi bi-car-front"></i> {{ __('Vehicle and owner') }}</div><div class="card-body small">
                <div class="fs-5 fw-semibold" dir="ltr">{{ $a->vehicle->plate() }}</div><div>{{ $a->vehicle->title() }}</div>
                <div>{{ __('Owner') }}: <a href="{{ route('customers.show', $a->customer) }}">{{ $a->customer->name }}</a> · <bdi dir="ltr">{{ $a->customer->phone }}</bdi></div>
                <div>{{ __('Job card') }}: @if($a->jobCard)<a href="{{ route('job-cards.show', $a->jobCard) }}">{{ $a->jobCard->number }}</a>@else {{ __('Any visit in the period') }} @endif</div>
                <div>{{ __('Valid') }}: {{ $a->valid_from?->format('Y-m-d H:i') }} → {{ $a->valid_until?->format('Y-m-d H:i') ?? __('until used') }} · {{ $a->single_use ? __('Single use') : __('Multiple use') }}</div>
            </div></div></div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-signpost-split"></i> {{ __('Handovers made with this authorization') }}</div>
            <ul class="list-group list-group-flush small">
                @forelse($uses as $u)
                    <li class="list-group-item d-flex gap-2"><span class="badge {{ $u->direction === 'in' ? 'text-bg-primary' : 'text-bg-success' }}">{{ $u->direction === 'in' ? __('Drop off') : __('Pick up') }}</span>
                        {{ $u->logged_at?->format('Y-m-d H:i') }} · @if($u->jobCard)<a href="{{ route('job-cards.show', $u->jobCard) }}">{{ $u->jobCard->number }}</a>@endif · {{ __('ID checked by') }} {{ $u->user?->name }}
                        <a class="ms-auto" href="{{ route('gate-logs.print', $u) }}" target="_blank" rel="noopener">{{ $u->reference() }}</a></li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('Not used yet.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-xl-4">
        @if($a->status === 'pending')
        @can('handover_authorizations.approve')
            <div class="card mb-3 border-warning"><div class="card-header"><i class="bi bi-phone"></i> {{ __('Approve with a code sent to the owner') }}</div><div class="card-body">
                @if($canSendCode)
                    <form method="post" action="{{ route('handover-authorizations.send-code', $a) }}" class="mb-2">@csrf
                        <button class="btn btn-outline-primary w-100"><i class="bi bi-send"></i> {{ $a->code_sent_at ? __('Send a new code') : __('Send code to :p', ['p' => $a->customer->phone]) }}</button></form>
                    @if($a->code_sent_at)
                        <form method="post" action="{{ route('handover-authorizations.approve', $a) }}" class="d-flex gap-2">@csrf<input type="hidden" name="method" value="code">
                            <input name="code" class="form-control font-monospace @error('code') is-invalid @enderror" inputmode="numeric" maxlength="6" placeholder="000000" aria-label="{{ __('Code') }}" dir="ltr">
                            <button class="btn btn-success text-nowrap">{{ __('Confirm') }}</button></form>
                        <div class="form-text">{{ __('Code sent :t. It is valid for 30 minutes.', ['t' => $a->code_sent_at->diffForHumans()]) }}</div>
                    @endif
                @else
                    <div class="small text-body-secondary">{{ __('SMS / WhatsApp is not configured, so a code cannot be sent. Use the signed letter below.') }}</div>
                @endif
            </div></div>
            <div class="card mb-3"><div class="card-header"><i class="bi bi-pen"></i> {{ __('Approve with the signed letter') }}</div><div class="card-body">
                <ol class="small ps-3"><li>{{ __('Print the authorization letter.') }}</li><li>{{ __('The owner signs it (in person, or sends it signed).') }}</li><li>{{ __('Keep it in the file and confirm here.') }}</li></ol>
                <form method="post" action="{{ route('handover-authorizations.approve', $a) }}">@csrf<input type="hidden" name="method" value="signature">
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="signed" value="1" id="signed" required><label class="form-check-label small" for="signed">{{ __('I received the letter signed by the owner and checked the owner\'s ID.') }}</label></div>
                    <button class="btn btn-success w-100"><i class="bi bi-check2-circle"></i> {{ __('Activate authorization') }}</button></form>
            </div></div>
        @endcan
        @endif
        @if(in_array($a->status, ['pending', 'active'], true))
        @can('handover_authorizations.update')
            <div class="card mb-3 border-danger-subtle"><div class="card-header text-danger"><i class="bi bi-x-octagon"></i> {{ __('Revoke') }}</div><div class="card-body">
                <form method="post" action="{{ route('handover-authorizations.revoke', $a) }}" data-confirm="{{ __('Revoke this authorization? It cannot be used afterwards.') }}">@csrf
                    <input name="reason" class="form-control mb-2" required placeholder="{{ __('Reason (e.g. requested by the owner)') }}" aria-label="{{ __('Reason') }}">
                    <button class="btn btn-outline-danger w-100">{{ __('Revoke authorization') }}</button></form>
            </div></div>
        @endcan
        @endif
    </div>
</div>
@endsection
