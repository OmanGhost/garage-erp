<div wire:poll.45s>
    <div class="card mb-3"><div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-md-4"><input type="search" class="form-control" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search number, plate, customer...') }}"></div>
            <div class="col-md-3"><select class="form-select" wire:model.live="department"><option value="">{{ __('All departments') }}</option>@foreach($departments as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
            <div class="col-md-3"><select class="form-select" wire:model.live="technician"><option value="">{{ __('All technicians') }}</option>@foreach($technicians as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
            <div class="col-md-2"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="kbDel" wire:model.live="showDelivered"><label class="form-check-label" for="kbDel">{{ __('Show delivered') }}</label></div></div>
        </div>
    </div></div>

    <div class="kanban" wire:ignore.self x-data x-init="
        const init = () => document.querySelectorAll('.kanban-list').forEach(el => {
            if (el._sortable) return;
            el._sortable = Sortable.create(el, { group: 'jobs', animation: 150, ghostClass: 'sortable-ghost',
                onMove: (evt) => { const from = evt.from.dataset.status, to = evt.to.dataset.status; const allowed = JSON.parse(evt.dragged.dataset.next || '[]'); return from === to || allowed.includes(to); },
                onEnd: (evt) => { if (evt.from === evt.to) return; $wire.move(parseInt(evt.item.dataset.id), evt.to.dataset.status); } });
        });
        init(); Livewire.hook('morph.updated', () => init());">
        @foreach($columns as $status)
            @php $list = $cards[$status] ?? collect(); @endphp
            <div class="kanban-col" style="--col: {{ $colors[$status] ?? '#999' }}">
                <div class="kanban-col-head"><span>{{ status_label($status) }}</span><span class="badge rounded-pill text-bg-secondary">{{ $list->count() }}</span></div>
                <div class="kanban-list" data-status="{{ $status }}" wire:key="col-{{ $status }}">
                    @foreach($list as $j)
                        @php $late = $j->promised_at && $j->promised_at->isPast() && $j->isOpen(); @endphp
                        <div class="kanban-card priority-{{ $j->priority }}" data-id="{{ $j->id }}" data-next='@json($transitions[$j->status] ?? [])' wire:key="card-{{ $j->id }}">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <a href="{{ route('job-cards.show', $j) }}" class="fw-bold text-decoration-none">{{ $j->number }}</a>
                                <span class="plate">{{ $j->vehicle?->plate() }}</span>
                            </div>
                            <div class="text-truncate">{{ $j->vehicle?->title() }}</div>
                            <div class="small text-body-secondary text-truncate"><i class="bi bi-person"></i> {{ $j->customer?->name }}</div>
                            @if($j->department)<div class="small text-body-secondary text-truncate"><i class="bi bi-diagram-3"></i> {{ $j->department->displayName() }}</div>@endif
                            @php $techs = $j->tasks->pluck('technician.name')->filter()->unique(); @endphp
                            @if($techs->isNotEmpty())<div class="small text-truncate"><i class="bi bi-wrench"></i> {{ $techs->implode(', ') }}</div>@endif
                            <div class="progress my-1" style="height:4px"><div class="progress-bar" style="width:{{ $j->progress }}%"></div></div>
                            <div class="d-flex justify-content-between small">
                                <span class="{{ $late ? 'text-danger fw-bold' : 'text-body-secondary' }}"><i class="bi bi-clock"></i> {{ $j->promised_at?->format('d/m H:i') ?? '—' }}</span>
                                @if($j->priority !== 'normal')<span class="badge text-bg-{{ $j->priority === 'urgent' ? 'danger' : ($j->priority === 'high' ? 'warning' : 'secondary') }}">{{ status_label($j->priority) }}</span>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
