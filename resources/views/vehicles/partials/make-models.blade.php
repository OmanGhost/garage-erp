<div class="card mb-3">
    <div class="card-header">{{ __('Models') }}</div>
    <div class="card-body d-flex flex-wrap gap-2">
        @forelse($make->models()->orderBy('name')->get() as $m)<span class="badge text-bg-light border">{{ $m->name }}</span>@empty<span class="text-body-secondary small">—</span>@endforelse
    </div>
</div>
