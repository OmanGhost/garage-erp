@extends('layouts.app')
@section('title', $audit->number)
@section('content')
@php $done = $audit->status === 'completed'; $can = ! $done && auth()->user()->can('quality_audits.update'); @endphp
<x-page-header :title="__('Internal audit').' '.$audit->number" icon="bi-clipboard2-data" :breadcrumbs="[__('Internal audits') => route('crud.index', 'quality-audits'), $audit->number]"
    :subtitle="e($audit->title).' · '.e($audit->area ?? '').' · '.status_badge($audit->status)">
    @can('quality_audits.update')@unless($done)<a href="{{ route('crud.edit', ['quality-audits', $audit->id]) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>@endunless @endcan
</x-page-header>

<div class="row g-3">
    <div class="col-xl-8">
        <form method="post" action="{{ route('quality.audits.record', $audit) }}" class="card">@csrf
            <div class="card-header d-flex"><i class="bi bi-list-check me-1"></i>{{ __('Audit checklist') }}@if($audit->score !== null)<span class="ms-auto badge text-bg-primary fs-6">{{ $audit->score }}%</span>@endif</div>
            <div class="list-group list-group-flush">
                @forelse($audit->checklist ?? [] as $i => $row)
                    <div class="list-group-item">
                        <div class="fw-semibold mb-1">{{ $i + 1 }}. {{ $row['q'] }}</div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <div class="btn-group btn-group-sm" role="group" aria-label="{{ $row['q'] }}">
                                @foreach(['conform' => ['success', __('Conform')], 'observation' => ['info', __('Observation')], 'nonconform' => ['danger', __('Nonconform')], 'na' => ['secondary', __('N/A')]] as $v => [$c, $l])
                                    <input type="radio" class="btn-check" name="answers[{{ $i }}][result]" id="a{{ $i }}{{ $v }}" value="{{ $v }}" @checked(($row['result'] ?? null) === $v) @disabled(! $can)>
                                    <label class="btn btn-outline-{{ $c }}" for="a{{ $i }}{{ $v }}">{{ $l }}</label>
                                @endforeach
                            </div>
                            <input name="answers[{{ $i }}][notes]" value="{{ $row['notes'] ?? '' }}" class="form-control form-control-sm flex-grow-1" style="min-width:200px" placeholder="{{ __('Evidence / notes') }}" aria-label="{{ __('Notes') }}" @disabled(! $can)>
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-body-secondary small">{{ __('Add the audit questions by editing the audit.') }}</div>
                @endforelse
            </div>
            <div class="card-body border-top">
                <label class="form-label small" for="a_sum">{{ __('Summary') }}</label>
                <textarea id="a_sum" name="summary" rows="3" class="form-control mb-2" @disabled(! $can)>{{ $audit->summary }}</textarea>
                @if($can)
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary"><i class="bi bi-save"></i> {{ __('Save') }}</button>
                        <button name="complete" value="1" class="btn btn-success ms-auto" data-confirm="{{ __('Complete the audit? Nonconforming items will be registered as NCRs.') }}"><i class="bi bi-check2-all"></i> {{ __('Complete audit') }}</button>
                    </div>
                @endif
            </div>
        </form>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-body doc-meta">
            <div><span>{{ __('Planned date') }}</span>{{ $audit->planned_date?->format('Y-m-d') }}</div>
            <div><span>{{ __('Auditor') }}</span>{{ $audit->auditor?->name ?? '—' }}</div>
            @if($audit->performed_at)<div><span>{{ __('Performed') }}</span>{{ $audit->performed_at->format('Y-m-d H:i') }}</div>@endif
        </div></div>
        <div class="card"><div class="card-header">{{ __('Nonconformities from this audit') }}</div><ul class="list-group list-group-flush small">
            @forelse($audit->nonconformities as $n)<li class="list-group-item d-flex gap-2"><a href="{{ route('quality.ncr.show', $n) }}">{{ $n->number }}</a><span class="text-truncate">{{ $n->title }}</span><span class="ms-auto">{!! status_badge($n->status) !!}</span></li>
            @empty<li class="list-group-item text-body-secondary">—</li>@endforelse
        </ul></div>
    </div>
</div>
@endsection
