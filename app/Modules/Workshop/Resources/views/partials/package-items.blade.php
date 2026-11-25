@php $items = $package->items()->with('service', 'part')->get(); @endphp
<div class="card mb-3">
    <div class="card-header">{{ __('Package contents') }}</div>
    <ul class="list-group list-group-flush">
        @forelse($items as $i)
            <li class="list-group-item d-flex">{{ $i->service?->displayName() ?? $i->part?->label() }}<span class="ms-auto">× {{ qty($i->qty) }}</span></li>
        @empty
            <li class="list-group-item text-body-secondary small">{{ __('No items.') }}</li>
        @endforelse
    </ul>
    <form method="post" action="{{ route('crud.update', ['service-packages', $package->id]) }}" class="d-none"></form>
</div>
