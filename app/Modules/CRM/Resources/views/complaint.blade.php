@extends('layouts.app')
@section('title', $c->displayNumber())
@section('content')
@php
    $colors = ['open' => 'danger', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'secondary'];
    $icons = ['created' => 'bi-flag-fill text-danger', 'status' => 'bi-arrow-repeat text-primary', 'assigned' => 'bi-person-check text-info',
              'resolution' => 'bi-check2-circle text-success', 'note' => 'bi-chat-left-text text-secondary'];
    $history = \App\Modules\CRM\Http\Controllers\ComplaintController::history($c)->reverse();
    $age = $c->created_at?->diffForHumans(($c->resolved_at ?? now()), \Carbon\CarbonInterface::DIFF_ABSOLUTE);
@endphp
<x-page-header :title="__('Complaint').' '.$c->displayNumber()" icon="bi-chat-left-dots"
    :breadcrumbs="[__('Complaints & Feedback') => route('crud.index', 'complaints'), $c->displayNumber()]"
    :subtitle="e($c->subject).' · '.status_badge($c->status).' '.status_badge($c->priority)">
    @can('complaints.print')
        <a class="btn btn-outline-secondary" href="{{ route('complaints.print', $c) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Print') }}</a>
        <a class="btn btn-outline-secondary" href="{{ route('complaints.print', [$c, 'pdf']) }}" target="_blank" rel="noopener"><i class="bi bi-filetype-pdf"></i> PDF</a>
    @endcan
    @can('complaints.update')<a class="btn btn-outline-primary" href="{{ route('crud.edit', ['complaints', $c->id]) }}"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>@endcan
</x-page-header>

<div class="row g-3 mb-3">
    @foreach([
        [__('Reference'), $c->displayNumber(), 'bi-hash', 'primary'],
        [__('Status'), status_label($c->status), 'bi-flag', $colors[$c->status] ?? 'secondary'],
        [__('Assigned to'), $c->assignee?->name ?? __('Not assigned'), 'bi-person-badge', 'info'],
        [$c->resolved_at ? __('Resolved in') : __('Open for'), $age, 'bi-stopwatch', $c->resolved_at ? 'success' : 'warning'],
    ] as [$l, $v, $icon, $col])
        <div class="col-6 col-lg-3"><div class="card kpi h-100"><div class="card-body d-flex align-items-center gap-3">
            <span class="kpi-icon bg-{{ $col }}-subtle text-{{ $col }}"><i class="bi {{ $icon }}"></i></span>
            <div><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-6">{{ $v }}</div></div>
        </div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-card-text"></i> {{ __('Complaint details') }}</div>
            <div class="card-body">
                <h5 class="mb-2">{{ $c->subject }}</h5>
                <div class="mb-3" style="white-space:pre-line">{{ $c->description ?: '—' }}</div>
                <dl class="row small mb-0">
                    <dt class="col-sm-3 text-body-secondary">{{ __('Customer') }}</dt>
                    <dd class="col-sm-9"><a href="{{ route('customers.show', $c->customer) }}">{{ $c->customer->name }}</a> · <bdi dir="ltr">{{ $c->customer->phone }}</bdi></dd>
                    <dt class="col-sm-3 text-body-secondary">{{ __('Job card') }}</dt>
                    <dd class="col-sm-9">@if($c->jobCard)<a href="{{ route('job-cards.show', $c->jobCard) }}">{{ $c->jobCard->number }}</a>@else — @endif</dd>
                    <dt class="col-sm-3 text-body-secondary">{{ __('Vehicle') }}</dt>
                    <dd class="col-sm-9">{{ $c->vehicle?->label() ?? $c->jobCard?->vehicle?->label() ?? '—' }}</dd>
                    <dt class="col-sm-3 text-body-secondary">{{ __('Source') }}</dt><dd class="col-sm-9">{{ status_label((string) $c->source) }}</dd>
                    <dt class="col-sm-3 text-body-secondary">{{ __('Date') }}</dt><dd class="col-sm-9">{{ $c->created_at?->format('Y-m-d H:i') }}</dd>
                </dl>
            </div>
            @if($c->resolution)
                <div class="card-footer bg-success-subtle"><div class="fw-semibold small mb-1"><i class="bi bi-check2-circle"></i> {{ __('Resolution') }}</div><div style="white-space:pre-line">{{ $c->resolution }}</div></div>
            @endif
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clock-history"></i> {{ __('Tracking history') }}</div>
            <ul class="list-group list-group-flush">
                @forelse($history as $h)
                    <li class="list-group-item d-flex gap-3">
                        <i class="bi {{ $icons[$h['type']] ?? 'bi-dot' }} fs-5"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $h['type'] === 'created' ? __('Complaint registered') : ($h['type'] === 'note' ? __('Note') : $h['text']) }}</div>
                            @if($h['type'] === 'note')<div style="white-space:pre-line">{{ $h['text'] }}</div>@endif
                            <div class="small text-body-secondary">{{ $h['at'] }} · {{ $h['user'] }}</div>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('No records found.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-xl-4">
        @can('complaints.update')
        <div class="card mb-3 position-sticky" style="top:1rem">
            <div class="card-header"><i class="bi bi-journal-check"></i> {{ __('Follow-up') }}</div>
            <div class="card-body">
                <form method="post" action="{{ route('complaints.follow-up', $c) }}" class="d-grid gap-2">@csrf
                    <label class="form-label small mb-0" for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" class="form-select">@foreach(\App\Modules\CRM\Models\Complaint::STATUSES as $s)<option value="{{ $s }}" @selected(old('status', $c->status) === $s)>{{ status_label($s) }}</option>@endforeach</select>
                    <label class="form-label small mb-0" for="assigned_to">{{ __('Assigned to') }}</label>
                    <select id="assigned_to" name="assigned_to" class="form-select"><option value="">—</option>@foreach($users as $id => $n)<option value="{{ $id }}" @selected((string) old('assigned_to', $c->assigned_to) === (string) $id)>{{ $n }}</option>@endforeach</select>
                    <label class="form-label small mb-0" for="note">{{ __('Follow-up note') }}</label>
                    <textarea id="note" name="note" rows="3" class="form-control" placeholder="{{ __('e.g. called the customer, part ordered…') }}">{{ old('note') }}</textarea>
                    <label class="form-label small mb-0" for="resolution">{{ __('Resolution') }}</label>
                    <textarea id="resolution" name="resolution" rows="3" class="form-control @error('resolution') is-invalid @enderror" placeholder="{{ __('Required to resolve or close') }}">{{ old('resolution', $c->resolution) }}</textarea>
                    @error('resolution')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-primary"><i class="bi bi-check2"></i> {{ __('Save follow-up') }}</button>
                </form>
            </div>
        </div>
        @endcan
    </div>
</div>
@endsection
