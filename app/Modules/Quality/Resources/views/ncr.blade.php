@extends('layouts.app')
@section('title', $ncr->number)
@section('content')
@php
    $closed = $ncr->status === 'closed';
    $steps = ['open' => __('Reported'), 'investigating' => __('Root cause'), 'action' => __('Actions'), 'verification' => __('Verification'), 'closed' => __('Closed')];
    $idx = array_search($ncr->status, array_keys($steps), true);
    $sev = ['critical' => 'danger', 'major' => 'warning', 'minor' => 'secondary'][$ncr->severity] ?? 'secondary';
@endphp
<x-page-header :title="__('Nonconformity').' '.$ncr->number" icon="bi-exclamation-diamond" :breadcrumbs="[__('Nonconformities (NCR)') => route('crud.index', 'nonconformities'), $ncr->number]"
    :subtitle="e($ncr->title)">
    @if(! $closed)@can('nonconformities.approve')<form method="post" action="{{ route('quality.ncr.close', $ncr) }}" data-confirm="{{ __('Close this nonconformity?') }}">@csrf<button class="btn btn-success"><i class="bi bi-check2-all"></i> {{ __('Close NCR') }}</button></form>@endcan @endif
</x-page-header>

<div class="card mb-3"><div class="card-body">
    <div class="d-flex flex-wrap gap-2 justify-content-between">
        @foreach($steps as $k => $l)
            @php $i = array_search($k, array_keys($steps), true); @endphp
            <div class="text-center flex-fill"><span class="badge rounded-pill {{ $i < $idx ? 'text-bg-success' : ($i === $idx ? 'text-bg-primary' : 'text-bg-light') }} fs-6">{{ $i + 1 }}</span><div class="small mt-1 {{ $i === $idx ? 'fw-semibold' : 'text-body-secondary' }}">{{ $l }}</div></div>
        @endforeach
    </div>
</div></div>

<div class="row g-3">
    <div class="col-xl-7">
        <form method="post" action="{{ route('quality.ncr.update', $ncr) }}" class="card mb-3">@csrf @method('put')
            <div class="card-header"><i class="bi bi-search me-1"></i>{{ __('Investigation') }}</div>
            <div class="card-body row g-2">
                <div class="col-md-4"><label class="form-label small mb-0" for="n_cat">{{ __('Category') }}</label><select id="n_cat" name="category" class="form-select form-select-sm" @disabled($closed)>@foreach(\App\Modules\Quality\Models\Nonconformity::CATEGORIES as $c)<option value="{{ $c }}" @selected($ncr->category === $c)>{{ status_label($c) }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label small mb-0" for="n_sev">{{ __('Severity') }}</label><select id="n_sev" name="severity" class="form-select form-select-sm" @disabled($closed)>@foreach(['minor', 'major', 'critical'] as $s)<option value="{{ $s }}" @selected($ncr->severity === $s)>{{ status_label($s) }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label small mb-0" for="n_resp">{{ __('Responsible') }}</label><select id="n_resp" name="responsible_id" class="form-select form-select-sm" @disabled($closed)><option value="">—</option>@foreach($staff as $id => $n)<option value="{{ $id }}" @selected($ncr->responsible_id === $id)>{{ $n }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label small mb-0" for="n_desc">{{ __('What happened') }}</label><textarea id="n_desc" name="description" rows="3" class="form-control" @disabled($closed)>{{ $ncr->description }}</textarea></div>
                <div class="col-md-8"><label class="form-label small mb-0" for="n_rc">{{ __('Root cause') }}</label><textarea id="n_rc" name="root_cause" rows="4" class="form-control" placeholder="{{ __('Why? → Why? → Why? … (5 Whys) or the cause found with the fishbone diagram') }}" @disabled($closed)>{{ $ncr->root_cause }}</textarea></div>
                <div class="col-md-4">
                    <label class="form-label small mb-0" for="n_m">{{ __('Analysis method') }}</label><select id="n_m" name="analysis_method" class="form-select form-select-sm mb-2" @disabled($closed)><option value="">—</option><option value="5why" @selected($ncr->analysis_method === '5why')>{{ __('5 Whys') }}</option><option value="fishbone" @selected($ncr->analysis_method === 'fishbone')>{{ __('Fishbone (Ishikawa)') }}</option><option value="other" @selected($ncr->analysis_method === 'other')>{{ __('Other') }}</option></select>
                    <label class="form-label small mb-0" for="n_cost">{{ __('Cost of poor quality') }}</label><input id="n_cost" type="number" step="0.001" min="0" name="cost" value="{{ $ncr->cost }}" class="form-control form-control-sm" @disabled($closed)>
                </div>
                @unless($closed)<div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> {{ __('Save') }}</button></div>@endunless
            </div>
        </form>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-check2-square me-1"></i>{{ __('Corrective & preventive actions (CAPA)') }}</div>
            <div class="list-group list-group-flush">
                @forelse($ncr->actions as $a)
                    <div class="list-group-item">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge {{ $a->type === 'preventive' ? 'text-bg-info' : 'text-bg-warning' }}">{{ $a->type === 'preventive' ? __('Preventive') : __('Corrective') }}</span>
                            <span class="fw-semibold">{{ $a->description }}</span>
                            <span class="ms-auto">{!! status_badge($a->status) !!}</span>
                        </div>
                        <div class="small text-body-secondary">{{ $a->owner?->name ?? '—' }} · {{ __('Due') }} <span class="{{ $a->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $a->due_date?->format('Y-m-d') ?? '—' }}</span>
                            @if($a->completed_at) · {{ __('Done') }} {{ $a->completed_at->format('Y-m-d') }}@endif
                            @if($a->verified_at) · {{ $a->effective ? __('Effective') : __('Not effective') }} ({{ $a->verifier?->name }})@endif</div>
                        @if($a->verification_notes)<div class="small">{{ $a->verification_notes }}</div>@endif
                        @unless($closed)
                            <div class="d-flex flex-wrap gap-2 mt-1">
                                @if(in_array($a->status, ['open', 'in_progress']))@can('capa_actions.update')<form method="post" action="{{ route('quality.actions.step', [$a, 'complete']) }}">@csrf<button class="btn btn-sm btn-outline-success">{{ __('Mark done') }}</button></form>@endcan @endif
                                @if($a->status === 'done')@can('capa_actions.approve')
                                    <form method="post" action="{{ route('quality.actions.step', [$a, 'verify']) }}" class="d-flex gap-1">@csrf
                                        <input name="notes" class="form-control form-control-sm" placeholder="{{ __('Effectiveness check') }}" aria-label="{{ __('Effectiveness check') }}">
                                        <button name="effective" value="1" class="btn btn-sm btn-success text-nowrap">{{ __('Effective') }}</button>
                                        <button name="effective" value="0" class="btn btn-sm btn-outline-danger text-nowrap">{{ __('Not effective') }}</button>
                                    </form>
                                @endcan @endif
                            </div>
                        @endunless
                    </div>
                @empty
                    <div class="list-group-item small text-body-secondary">{{ __('No actions yet.') }}</div>
                @endforelse
            </div>
            @if(! $closed)@can('capa_actions.create')
            <form method="post" action="{{ route('quality.ncr.actions', $ncr) }}" class="card-body border-top row g-2">@csrf
                <div class="col-md-3"><select name="type" class="form-select form-select-sm" aria-label="{{ __('Type') }}"><option value="corrective">{{ __('Corrective') }}</option><option value="preventive">{{ __('Preventive') }}</option></select></div>
                <div class="col-md-9"><input name="description" class="form-control form-control-sm" placeholder="{{ __('Action to take') }}" aria-label="{{ __('Action to take') }}" required></div>
                <div class="col-md-5"><select name="owner_id" class="form-select form-select-sm" aria-label="{{ __('Owner') }}"><option value="">{{ __('Owner') }}</option>@foreach($staff as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
                <div class="col-md-4"><input type="date" name="due_date" class="form-control form-control-sm" value="{{ today()->addDays(14)->toDateString() }}" aria-label="{{ __('Due') }}"></div>
                <div class="col-md-3 d-grid"><button class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> {{ __('Add') }}</button></div>
            </form>
            @endcan @endif
        </div>
        <x-attachments :model="$ncr" :title="__('Evidence & photos')"/>
    </div>
    <div class="col-xl-5">
        <div class="card mb-3"><div class="card-body doc-meta">
            <div><span>{{ __('Status') }}</span>{!! status_badge($ncr->status) !!}</div>
            <div><span>{{ __('Severity') }}</span><span class="badge text-bg-{{ $sev }}">{{ status_label($ncr->severity) }}</span></div>
            <div><span>{{ __('Source') }}</span>{{ status_label($ncr->source) }}</div>
            <div><span>{{ __('Detected on') }}</span>{{ $ncr->detected_at?->format('Y-m-d') }} · {{ $ncr->detector?->name }}</div>
            @if($ncr->jobCard)<div><span>{{ __('Job card') }}</span><a href="{{ route('job-cards.show', $ncr->jobCard) }}">{{ $ncr->jobCard->number }}</a> · {{ $ncr->jobCard->vehicle?->plate() }}</div>@endif
            @if($ncr->technician)<div><span>{{ __('Technician') }}</span>{{ $ncr->technician->name }}</div>@endif
            @if($ncr->supplier)<div><span>{{ __('Supplier') }}</span><a href="{{ route('suppliers.show', $ncr->supplier) }}">{{ $ncr->supplier->name }}</a></div>@endif
            @if($ncr->part)<div><span>{{ __('Part') }}</span>{{ $ncr->part->label() }}</div>@endif
            @if($ncr->audit)<div><span>{{ __('Internal audit') }}</span><a href="{{ route('quality.audits.show', $ncr->audit) }}">{{ $ncr->audit->number }}</a></div>@endif
            @if($ncr->closed_at)<div><span>{{ __('Closed') }}</span>{{ $ncr->closed_at->format('Y-m-d H:i') }}</div>@endif
        </div></div>
        <div class="alert alert-light small"><i class="bi bi-info-circle"></i> {{ __('To close: record the root cause, complete every action and have someone verify it was effective.') }}</div>
    </div>
</div>
@endsection
