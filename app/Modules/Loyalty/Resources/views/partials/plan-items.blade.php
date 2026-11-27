<div class="card mt-3"><div class="card-header fw-semibold">{{ __('Included services') }}</div><ul class="list-group list-group-flush small">
    @forelse($plan->items as $i)<li class="list-group-item d-flex"><span>{{ $i->service?->code }} — {{ $i->service?->displayName() }}</span><span class="ms-auto badge text-bg-primary">× {{ $i->qty }}</span></li>
    @empty<li class="list-group-item text-body-secondary">—</li>@endforelse
</ul></div>
