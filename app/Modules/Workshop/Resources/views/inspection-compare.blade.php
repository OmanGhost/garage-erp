@extends('layouts.app')
@section('title', __('Before / after repair'))
@section('content')
@php
    $Z = \App\Modules\Workshop\Models\Inspection::class;
    $trend = ['better' => ['bi-arrow-up-circle-fill', 'text-success', __('Improved')], 'worse' => ['bi-arrow-down-circle-fill', 'text-danger', __('Worse')], 'same' => ['bi-dash-circle', 'text-body-secondary', __('No change')], 'missing' => ['bi-question-circle', 'text-body-secondary', __('Not checked')]];
    $val = fn ($r) => $r && $r->value !== null ? rtrim(rtrim((string) $r->value, '0'), '.').' '.$r->unit : '';
    $improved = collect($rows)->where('trend', 'better')->count();
@endphp
<x-page-header :title="__('Before / after repair')" icon="bi-layout-split" :breadcrumbs="[__('Job Cards') => route('crud.index', 'job-cards'), $jc->number => route('job-cards.show', $jc), __('Before / after repair')]"
    :subtitle="e($jc->vehicle->label()).' · '.e($jc->customer->name)">
    <a href="{{ route('inspections.show', $before) }}" class="btn btn-outline-primary">{{ __('Before repair') }}</a>
    <a href="{{ route('inspections.show', $after) }}" class="btn btn-outline-success">{{ __('After repair') }}</a>
</x-page-header>

<div class="row g-3 mb-3">
    @foreach([[__('Before repair'), $before, 'primary'], [__('After repair'), $after, 'success']] as [$label, $ins, $c])
        @php $cnt = $ins->lightCounts(); @endphp
        <div class="col-md-6"><div class="card h-100 border-{{ $c }}">
            <div class="card-header d-flex"><b>{{ $label }}</b><span class="ms-auto small">{{ $ins->started_at?->format('Y-m-d H:i') }}</span></div>
            <div class="card-body">
                <div class="light-counter mb-2"><div class="g">{{ $cnt['green'] }}</div><div class="y">{{ $cnt['yellow'] }}</div><div class="r">{{ $cnt['red'] }}</div></div>
                @include('workshop::partials.dvi-diagram', ['lights' => $ins->zoneLights(), 'interactive' => false])
                @php $pics = $ins->findings->flatMap->attachments->filter->isImage(); @endphp
                @if($pics->isNotEmpty())<div class="d-flex flex-wrap gap-1 mt-2">@foreach($pics->take(8) as $a)<a href="{{ $a->url() }}" target="_blank" rel="noopener"><img src="{{ $a->url() }}" class="dvi-thumb" alt="" loading="lazy"></a>@endforeach</div>@endif
            </div>
        </div></div>
    @endforeach
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-list-check me-1"></i>{{ __('Checklist comparison') }} <span class="badge text-bg-success ms-1">{{ $improved }} {{ __('Improved') }}</span></div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light small"><tr><th>{{ __('Item') }}</th><th>{{ __('Before repair') }}</th><th>{{ __('After repair') }}</th><th></th></tr></thead>
        <tbody>
        @forelse(collect($rows)->groupBy('section') as $section => $items)
            <tr class="table-light"><th colspan="4" class="small">{{ $section }}</th></tr>
            @foreach($items as $row)
                @php [$ic, $tc, $tl] = $trend[$row['trend']]; @endphp
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td><span class="light-dot light-{{ $Z::light($row['before']->result) }}"></span> {{ status_label($row['before']->result) }} <span class="small text-body-secondary">{{ $val($row['before']) }}</span></td>
                    <td>@if($row['after'])<span class="light-dot light-{{ $Z::light($row['after']->result) }}"></span> {{ status_label($row['after']->result) }} <span class="small text-body-secondary">{{ $val($row['after']) }}</span>@else — @endif</td>
                    <td class="text-end"><span class="{{ $tc }}" title="{{ $tl }}"><i class="bi {{ $ic }}"></i> <span class="small">{{ $tl }}</span></span></td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="4" class="text-body-secondary">—</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
