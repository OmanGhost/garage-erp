<div class="position-relative" x-data="{ open: false }" @click.outside="open = false">
    <div class="input-group input-group-sm">
        <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
        <input type="search" class="form-control" wire:model.live.debounce.300ms="q" @focus="open = true" @input="open = true"
               placeholder="{{ __('Search plate, VIN, job card, customer, part, invoice...') }}" aria-label="{{ __('Global search') }}" data-scan-target>
    </div>
    @if(mb_strlen(trim($q)) >= 2)
        <div class="lookup-menu show shadow" x-show="open" style="max-height:70vh">
            @forelse($results as $group => $items)
                <div class="px-3 pt-2 pb-1 small text-uppercase text-body-secondary fw-semibold">{{ $group }}</div>
                @foreach($items as $r)
                    <a href="{{ $r['url'] }}" class="lookup-item d-flex gap-2 text-decoration-none text-body">
                        <i class="bi {{ $r['icon'] }} text-brand"></i>
                        <div class="min-w-0"><div class="text-truncate fw-semibold">{{ $r['title'] }}</div><div class="small text-body-secondary text-truncate">{{ $r['sub'] }}</div></div>
                    </a>
                @endforeach
            @empty
                <div class="lookup-item text-body-secondary">{{ __('No results') }}</div>
            @endforelse
        </div>
    @endif
</div>
