@extends('layouts.app')
@section('title', __('Workshop planner'))
@section('content')
@php
    [$open, $close] = $plan['window'];
    $span = max(1, $open->diffInMinutes($close));
    $pos = function ($s, $e) use ($open, $close, $span) {
        $a = $s->lt($open) ? $open : $s; $b = $e->gt($close) ? $close : $e;
        return ['start' => round($open->diffInMinutes($a) * 100 / $span, 3), 'width' => max(1.2, round($a->diffInMinutes($b) * 100 / $span, 3))];
    };
    $rows = $view === 'technicians'
        ? $technicians->map(fn ($u) => ['id' => $u->id, 'label' => $u->name, 'sub' => null, 'key' => 'technician_id'])
        : $bays->map(fn ($b) => ['id' => $b->id, 'label' => $b->code.' — '.$b->name, 'sub' => status_label($b->type).($b->has_lift ? ' · '.__('Lift') : ''), 'key' => 'bay_id']);
    $hours = []; for ($h = $open->copy()->minute(0); $h->lt($close); $h->addHour()) { if ($h->gte($open)) $hours[] = $h->copy(); }
    $taskTone = ['pending' => 'primary', 'in_progress' => 'warning', 'paused' => 'secondary', 'done' => 'success'];
    $isToday = $day->isToday();
    $nowPct = $isToday && now()->between($open, $close) ? round($open->diffInMinutes(now()) * 100 / $span, 3) : null;
    $canEdit = auth()->user()->can('workshop_planning.update');
@endphp
<x-page-header :title="__('Workshop planner')" icon="bi-calendar-range" :breadcrumbs="[__('Workshop'), __('Workshop planner')]">
    <a href="{{ route('workshop.live') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-tv"></i> {{ __('Live screen') }}</a>
    @if($canEdit && $queue->isNotEmpty())
        <form method="post" action="{{ route('workshop.planner.auto-all') }}" data-confirm="{{ __('Schedule all waiting tasks automatically?') }}">@csrf<button class="btn btn-primary"><i class="bi bi-magic"></i> {{ __('Auto-schedule all') }}</button></form>
    @endif
</x-page-header>

{{-- today --}}
<div class="row g-2 mb-3">
    @foreach([
        [__('Bay occupancy today'), $today['occupancy'].'%', __(':n bays', ['n' => count($today['bays'])]), 'bi-grid-3x3-gap', 'primary'],
        [__('Technician load today'), (count($today['technicians']) ? round(collect($today['technicians'])->avg('load'), 1) : 0).'%', __(':n technicians', ['n' => count($today['technicians'])]), 'bi-person-gear', 'info'],
        [__('Waiting to be scheduled'), $queue->sum(fn ($j) => $j->tasks->count()), trans_choice('{0} no job cards|{1} 1 job card|[2,*] :n job cards', $queue->count(), ['n' => $queue->count()]), 'bi-hourglass-split', 'warning'],
        [__('Late jobs'), $today['late'], __('Past the promised time'), 'bi-alarm', 'danger'],
    ] as [$l, $v, $sub, $icon, $c])
        <div class="col-6 col-lg-3"><div class="card kpi h-100"><div class="card-body d-flex align-items-center gap-3">
            <span class="kpi-icon bg-{{ $c }}-subtle text-{{ $c }}"><i class="bi {{ $icon }}"></i></span>
            <div class="min-w-0"><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-5">{{ $v }}</div><div class="small text-body-secondary">{{ $sub }}</div></div>
        </div></div></div>
    @endforeach
</div>

{{-- day grid --}}
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm">
            <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['date' => $day->copy()->subDay()->toDateString()]) }}" aria-label="{{ __('Previous day') }}"><i class="bi bi-chevron-{{ is_rtl() ? 'right' : 'left' }}"></i></a>
            <a class="btn btn-outline-secondary {{ $isToday ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['date' => today()->toDateString()]) }}">{{ __('Today') }}</a>
            <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['date' => $day->copy()->addDay()->toDateString()]) }}" aria-label="{{ __('Next day') }}"><i class="bi bi-chevron-{{ is_rtl() ? 'left' : 'right' }}"></i></a>
        </div>
        <form class="d-flex gap-1"><input type="hidden" name="view" value="{{ $view }}"><input type="date" name="date" value="{{ $day->toDateString() }}" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="{{ __('Date') }}"></form>
        <span class="fw-semibold">{{ $day->translatedFormat('l j F Y') }}</span>
        @unless($plan['open'])<span class="badge text-bg-secondary">{{ __('Closed') }}</span>@endunless
        <div class="btn-group btn-group-sm ms-auto" role="group">
            <a class="btn btn-outline-primary {{ $view === 'bays' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'bays']) }}"><i class="bi bi-grid-3x3-gap"></i> {{ __('Bays') }}</a>
            <a class="btn btn-outline-primary {{ $view === 'technicians' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'technicians']) }}"><i class="bi bi-person-gear"></i> {{ __('Technicians') }}</a>
        </div>
    </div>
    <div class="plan-scroll">
        <div class="plan-grid">
            <div class="plan-row plan-head">
                <div class="plan-label"></div>
                <div class="plan-track">
                    @foreach($hours as $h)<span class="plan-hour" style="inset-inline-start:{{ round($open->diffInMinutes($h) * 100 / $span, 3) }}%">{{ $h->format('H:i') }}</span>@endforeach
                </div>
            </div>
            @forelse($rows as $r)
                @php
                    $items = $plan['tasks']->where($r['key'], $r['id']);
                    $appts = $plan['appointments']->where($r['key'], $r['id']);
                    $booked = $items->sum(fn ($t) => max(0, ($t->scheduled_start->lt($open) ? $open : $t->scheduled_start)->diffInMinutes($t->scheduled_end->gt($close) ? $close : $t->scheduled_end)));
                @endphp
                <div class="plan-row">
                    <div class="plan-label"><div class="fw-semibold text-truncate">{{ $r['label'] }}</div><div class="small text-body-secondary">@if($r['sub']){{ $r['sub'] }} · @endif{{ round($booked * 100 / $span) }}%</div></div>
                    <div class="plan-track">
                        @foreach($hours as $h)<span class="plan-line" style="inset-inline-start:{{ round($open->diffInMinutes($h) * 100 / $span, 3) }}%"></span>@endforeach
                        @if($nowPct !== null)<span class="plan-now" style="inset-inline-start:{{ $nowPct }}%"></span>@endif
                        @foreach($items as $t)
                            @php $p = $pos($t->scheduled_start, $t->scheduled_end); $late = $t->status !== 'done' && $t->scheduled_end->isPast(); @endphp
                            <a href="{{ route('job-cards.show', $t->job_card_id) }}" class="plan-block bg-{{ $taskTone[$t->status] ?? 'primary' }}-subtle border-{{ $late ? 'danger' : ($taskTone[$t->status] ?? 'primary') }} text-body"
                               style="inset-inline-start:{{ $p['start'] }}%;width:{{ $p['width'] }}%"
                               title="{{ $t->jobCard->number }} · {{ $t->jobCard->vehicle?->plate() }} · {{ $t->description }} · {{ $t->scheduled_start->format('H:i') }}–{{ $t->scheduled_end->format('H:i') }} · {{ $view === 'bays' ? $t->technician?->name : $t->bay?->code }}">
                                <b>{{ $t->jobCard->vehicle?->plate() }}</b> <span class="d-none d-md-inline">{{ \Illuminate\Support\Str::limit($t->description, 40) }}</span>
                                <small class="d-block text-truncate">{{ $t->scheduled_start->format('H:i') }} · {{ $view === 'bays' ? ($t->technician?->name ?? '—') : ($t->bay?->code ?? '—') }}</small>
                            </a>
                        @endforeach
                        @foreach($appts as $a)
                            @php $p = $pos($a->starts_at, $a->ends_at); @endphp
                            <a href="{{ route('appointments.show', $a) }}" class="plan-block plan-appt" style="inset-inline-start:{{ $p['start'] }}%;width:{{ $p['width'] }}%" title="{{ __('Appointment') }} {{ $a->number }} · {{ $a->customer?->name }} · {{ $a->starts_at->format('H:i') }}–{{ $a->ends_at->format('H:i') }}">
                                <b><i class="bi bi-calendar-event"></i> {{ $a->vehicle?->plate() ?? $a->customer?->name }}</b><small class="d-block">{{ $a->starts_at->format('H:i') }}</small>
                            </a>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-body-secondary">
                    @if($view === 'bays'){{ __('No workshop bays yet.') }} @can('workshop_bays.create')<a href="{{ route('crud.create', 'workshop-bays') }}">{{ __('Add a bay') }}</a>@endcan
                    @else{{ __('No technicians yet. Mark employees as technicians in HR.') }}@endif
                </div>
            @endforelse
        </div>
    </div>
    <div class="card-footer small text-body-secondary d-flex flex-wrap gap-3">
        <span><span class="plan-key bg-primary-subtle border-primary"></span> {{ status_label('pending') }}</span>
        <span><span class="plan-key bg-warning-subtle border-warning"></span> {{ status_label('in_progress') }}</span>
        <span><span class="plan-key bg-success-subtle border-success"></span> {{ status_label('done') }}</span>
        <span><span class="plan-key border-danger"></span> {{ __('Running late') }}</span>
        <span><span class="plan-key plan-appt"></span> {{ __('Appointment') }}</span>
    </div>
</div>

<div class="row g-3">
    {{-- scheduling queue --}}
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-list-task me-1"></i>{{ __('Waiting to be scheduled') }}</div>
            <div class="list-group list-group-flush">
                @forelse($queue as $jc)
                    @php $eta = app(\App\Modules\Workshop\Services\CapacityService::class)->forecast($jc); @endphp
                    <div class="list-group-item">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <a href="{{ route('job-cards.show', $jc) }}" class="fw-semibold">{{ $jc->number }}</a>
                            <span class="badge text-bg-light">{{ $jc->vehicle?->plate() }}</span>
                            @if(in_array($jc->priority, ['high', 'urgent']))<span class="badge text-bg-danger">{{ status_label($jc->priority) }}</span>@endif
                            <span class="small text-body-secondary">{{ $jc->customer?->name }}</span>
                            <span class="small ms-auto">{{ __('Estimated completion') }}: <b>{{ $eta?->format('Y-m-d H:i') ?? '—' }}</b></span>
                            @if($canEdit)<form method="post" action="{{ route('workshop.planner.auto', $jc) }}">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-magic"></i> {{ __('Auto') }}</button></form>@endif
                        </div>
                        @foreach($jc->tasks as $t)
                            <form method="post" action="{{ route('workshop.planner.schedule', $t) }}" class="row g-1 align-items-center small mb-1">@csrf
                                <div class="col-12 col-md-4 text-truncate"><i class="bi bi-wrench"></i> {{ $t->description }} <span class="text-body-secondary">({{ $t->plannedMinutes() }} {{ __('min') }})</span></div>
                                @if($canEdit)
                                <div class="col-4 col-md-3"><select name="technician_id" class="form-select form-select-sm" aria-label="{{ __('Technician') }}"><option value="">{{ __('Technician') }}</option>@foreach($technicians as $u)<option value="{{ $u->id }}" @selected($t->technician_id === $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                                <div class="col-3 col-md-2"><select name="bay_id" class="form-select form-select-sm" aria-label="{{ __('Bay') }}"><option value="">{{ __('Bay') }}</option>@foreach($bays as $b)<option value="{{ $b->id }}" @selected(($t->bay_id ?? $jc->bay_id) === $b->id)>{{ $b->code }}</option>@endforeach</select></div>
                                <div class="col-5 col-md-3 d-flex gap-1"><input type="datetime-local" name="start" class="form-control form-control-sm" required value="{{ now()->addMinutes(5 - now()->minute % 5)->format('Y-m-d\TH:i') }}" aria-label="{{ __('Start') }}"><button class="btn btn-sm btn-outline-primary" title="{{ __('Schedule') }}" aria-label="{{ __('Schedule') }}"><i class="bi bi-check2"></i></button></div>
                                @endif
                            </form>
                        @endforeach
                    </div>
                @empty
                    <div class="list-group-item"><div class="empty-state py-3"><i class="bi bi-check2-all"></i>{{ __('All approved tasks are scheduled.') }}</div></div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- utilization --}}
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center"><i class="bi bi-speedometer me-1"></i>{{ __('Occupancy & productivity') }}
                <div class="btn-group btn-group-sm ms-auto">
                    <a class="btn btn-outline-secondary {{ $period === 'week' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['period' => 'week']) }}">{{ __('This week') }}</a>
                    <a class="btn btn-outline-secondary {{ $period === 'month' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['period' => 'month']) }}">{{ __('This month') }}</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2 text-center mb-3">
                    @foreach([[__('Bay occupancy'), $stats['occupancy'].'%'], [__('Productivity'), $stats['productivity'].'%'], [__('Efficiency'), $stats['efficiency'].'%'], [__('Jobs completed'), $stats['completed']], [__('Avg. turnaround'), $stats['turnaround_hours'] !== null ? $stats['turnaround_hours'].' '.__('h') : '—']] as [$l, $v])
                        <div class="col"><div class="small text-body-secondary">{{ $l }}</div><div class="fw-bold fs-5">{{ $v }}</div></div>
                    @endforeach
                </div>
                <div class="small fw-semibold mb-1">{{ __('Bays') }}</div>
                @foreach($stats['bays'] as $b)
                    <div class="d-flex align-items-center gap-2 small mb-1"><span class="text-truncate" style="width:35%">{{ $b['bay']->code }} — {{ $b['bay']->name }}</span>
                        <div class="progress flex-grow-1" style="height:8px" role="progressbar" aria-valuenow="{{ $b['rate'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $b['bay']->code }}"><div class="progress-bar {{ $b['rate'] > 90 ? 'bg-danger' : ($b['rate'] > 70 ? 'bg-warning' : '') }}" style="width:{{ $b['rate'] }}%"></div></div>
                        <span class="font-monospace" style="width:3.5rem">{{ $b['rate'] }}%</span></div>
                @endforeach
                <div class="table-responsive mt-3"><table class="table table-sm align-middle mb-0 small">
                    <thead><tr><th>{{ __('Technician') }}</th><th class="text-end">{{ __('Load') }}</th><th class="text-end">{{ __('Productivity') }}</th><th class="text-end">{{ __('Efficiency') }}</th><th class="text-end">{{ __('Tasks') }}</th></tr></thead>
                    <tbody>@forelse($stats['technicians'] as $t)
                        <tr><td>{{ $t['user']->name }}</td><td class="text-end">{{ $t['load'] }}%</td><td class="text-end">{{ $t['productivity'] }}%</td>
                            <td class="text-end {{ $t['efficiency'] !== null && $t['efficiency'] < 80 ? 'text-danger' : '' }}">{{ $t['efficiency'] !== null ? $t['efficiency'].'%' : '—' }}</td><td class="text-end">{{ $t['tasks'] }}</td></tr>
                    @empty<tr><td colspan="5" class="text-body-secondary">—</td></tr>@endforelse</tbody>
                </table></div>
                <div class="form-text">{{ __('Load = booked / available hours · Productivity = clocked / available hours · Efficiency = standard (estimated) / clocked time of finished tasks.') }}</div>
            </div>
        </div>
    </div>
</div>
@can('settings.update')
    @php $tvToken = setting('workshop_tv_token'); @endphp
    <div class="card mt-3"><div class="card-body d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-tv fs-4 text-brand"></i>
        <div class="flex-grow-1 min-w-0"><div class="fw-semibold">{{ __('Live screen on a workshop TV') }}</div>
            <div class="small text-body-secondary">{{ __('Open this secret link on the TV browser — no sign-in needed; customer surnames are hidden.') }}</div>
            @if($tvToken)<input class="form-control form-control-sm font-monospace mt-1" readonly value="{{ route('workshop.tv', $tvToken) }}" onclick="this.select()" aria-label="{{ __('TV link') }}">@endif</div>
        <form method="post" action="{{ route('workshop.live.token') }}" @if($tvToken) data-confirm="{{ __('Create a new link? The current TV link will stop working.') }}" @endif>@csrf<button class="btn btn-sm btn-outline-primary">{{ $tvToken ? __('New link') : __('Create TV link') }}</button></form>
    </div></div>
@endcan
@endsection
