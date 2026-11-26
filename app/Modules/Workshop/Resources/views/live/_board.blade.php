@php
    $who = fn ($jc) => $public ? \Illuminate\Support\Str::before((string) $jc->customer?->name, ' ') : $jc->customer?->name;
    $card = function ($jc, $cls = '') use ($who) {
        $tasks = $jc->tasks->whereNotIn('status', ['cancelled']);
        $done = $tasks->where('status', 'done')->count();
        $pct = $tasks->count() ? round($done * 100 / $tasks->count()) : 0;
        $current = $tasks->firstWhere('status', 'in_progress') ?? $tasks->whereIn('status', ['pending', 'paused'])->sortBy('scheduled_start')->first();
        $due = $jc->promised_at ?? $jc->estimated_completion_at;
        return compact('tasks', 'done', 'pct', 'current', 'due') + ['cls' => $jc->isLate() ? 'late' : $cls, 'who' => $who($jc)];
    };
@endphp
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach([[__('In the workshop'), $working->count(), '#3b82f6'], [__('Waiting'), $waiting->count(), '#a78bfa'], [__('Ready for delivery'), $ready->count(), '#22c55e'], [__('Running late'), $late->count(), '#ef4444'], [__('Appointments today'), $appointments->count(), '#f59e0b'], [__('Delivered today'), $deliveredToday, '#94a3b8']] as [$l, $n, $c])
        <div class="tv-kpi"><b style="color:{{ $c }}">{{ $n }}</b><small>{{ $l }}</small></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-xl-4 col-lg-6">
        <div class="tv-col">
            <h2><i class="bi bi-wrench-adjustable" style="color:#3b82f6"></i> {{ __('In the workshop') }} <span class="badge text-bg-primary">{{ $working->count() }}</span></h2>
            @forelse($working as $jc)
                @php $c = $card($jc, $jc->status === 'on_hold' ? 'hold' : ''); @endphp
                <div class="tv-card {{ $c['cls'] }}">
                    <div class="d-flex align-items-center gap-2">
                        <span class="tv-plate">{{ $jc->vehicle?->plate() }}</span>
                        <span class="tv-sub text-truncate">{{ $jc->vehicle?->title() }}</span>
                        @if($jc->bay)<span class="badge text-bg-secondary ms-auto">{{ $jc->bay->code }}</span>@endif
                    </div>
                    <div class="tv-sub mt-1">
                        {{ status_label($jc->status) }}@if($c['current']) · {{ \Illuminate\Support\Str::limit($c['current']->description, 40) }}@if($c['current']->technician) — {{ $c['current']->technician->name }}@endif @endif
                    </div>
                    <div class="d-flex tv-sub small mt-1"><span>{{ $jc->number }} · {{ $c['who'] }}</span>@if($c['due'])<span class="ms-auto {{ $c['cls'] === 'late' ? 'text-danger fw-bold' : '' }}"><i class="bi bi-clock"></i> {{ $c['due']->format($c['due']->isToday() ? 'H:i' : 'd/m H:i') }}</span>@endif</div>
                    @if($c['tasks']->count())<div class="tv-progress"><span style="width:{{ $c['pct'] }}%"></span></div>@endif
                </div>
            @empty
                <div class="tv-empty"><i class="bi bi-cup-hot fs-1 d-block"></i>{{ __('No vehicles in progress.') }}</div>
            @endforelse
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="tv-col">
            <h2><i class="bi bi-hourglass-split" style="color:#a78bfa"></i> {{ __('Waiting') }} <span class="badge" style="background:#6d28d9">{{ $waiting->count() }}</span></h2>
            @forelse($waiting->take(14) as $jc)
                @php $c = $card($jc); @endphp
                <div class="tv-card {{ $c['cls'] }}" style="border-inline-start-color:#a78bfa">
                    <div class="d-flex align-items-center gap-2"><span class="tv-plate fs-5">{{ $jc->vehicle?->plate() }}</span>
                        @if(in_array($jc->priority, ['high', 'urgent']))<span class="badge text-bg-danger">{{ status_label($jc->priority) }}</span>@endif</div>
                    <div class="tv-sub small">{{ status_label($jc->status) }} · {{ $jc->received_at?->format('H:i') }}</div>
                </div>
            @empty
                <div class="tv-empty">—</div>
            @endforelse
            @if($waiting->count() > 14)<div class="tv-sub text-center">+{{ $waiting->count() - 14 }}</div>@endif
        </div>
    </div>
    <div class="col-xl-2 col-lg-6">
        <div class="tv-col">
            <h2><i class="bi bi-check2-circle" style="color:#22c55e"></i> {{ __('Ready') }} <span class="badge text-bg-success">{{ $ready->count() }}</span></h2>
            @forelse($ready as $jc)
                <div class="tv-card ready"><span class="tv-plate fs-5">{{ $jc->vehicle?->plate() }}</span><div class="tv-sub small">{{ $who($jc) }}</div></div>
            @empty
                <div class="tv-empty">—</div>
            @endforelse
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="tv-col">
            @if($late->isNotEmpty() || $lateTasks->isNotEmpty())
                <h2><i class="bi bi-alarm" style="color:#ef4444"></i> {{ __('Running late') }}</h2>
                @foreach($late->take(6) as $jc)
                    <div class="tv-alert d-flex align-items-center gap-2"><span class="tv-plate fs-6">{{ $jc->vehicle?->plate() }}</span><span class="tv-sub small">{{ __('Promised') }} {{ ($jc->promised_at ?? $jc->estimated_completion_at)?->format('d/m H:i') }}</span></div>
                @endforeach
                @foreach($lateTasks->take(6) as $t)
                    <div class="tv-alert small"><b>{{ $t->jobCard?->vehicle?->plate() }}</b> · {{ \Illuminate\Support\Str::limit($t->description, 30) }} <span class="tv-sub">— {{ $t->technician?->name ?? '—' }} · {{ $t->scheduled_end->format('H:i') }}</span></div>
                @endforeach
                <hr class="border-secondary">
            @endif
            <h2><i class="bi bi-calendar-event" style="color:#f59e0b"></i> {{ __('Appointments today') }} <span class="badge text-bg-warning">{{ $appointments->count() }}</span></h2>
            @forelse($appointments as $a)
                <div class="tv-appt {{ in_array($a->status, ['checked_in', 'completed']) ? 'opacity-50' : '' }}">
                    <time>{{ $a->starts_at->format('H:i') }}</time>
                    <div class="min-w-0"><div class="fw-semibold text-truncate">{{ $a->vehicle?->plate() ?? ($public ? \Illuminate\Support\Str::before((string) $a->customer?->name, ' ') : $a->customer?->name) }}</div><div class="tv-sub small text-truncate">{{ $a->service?->displayName() ?? '' }}</div></div>
                    <span class="badge ms-auto {{ $a->status === 'checked_in' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ status_label($a->status) }}</span>
                </div>
            @empty
                <div class="tv-empty">{{ __('No appointments today.') }}</div>
            @endforelse
        </div>
    </div>
</div>
