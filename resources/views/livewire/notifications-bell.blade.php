<div class="dropdown" wire:poll.60s>
    <button class="btn btn-icon" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="{{ __('Notifications') }}">
        <i class="bi bi-bell fs-5"></i>
        @if($unread)<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger" style="font-size:.6rem;margin-top:8px;margin-inline-start:-8px">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow p-0" style="width:340px">
        <div class="d-flex align-items-center px-3 py-2 border-bottom">
            <strong>{{ __('Notifications') }}</strong>
            @if($unread)<button class="btn btn-link btn-sm ms-auto" wire:click="markAllRead">{{ __('Mark all as read') }}</button>@endif
        </div>
        <div style="max-height:360px;overflow:auto">
            @forelse($items as $n)
                <a href="{{ $n->data['url'] ?? '#' }}" wire:click="markRead('{{ $n->id }}')" class="d-block px-3 py-2 border-bottom text-decoration-none text-body {{ $n->read_at ? '' : 'bg-body-tertiary' }}">
                    <div class="fw-semibold small">{{ $n->data['title'] ?? '' }}</div>
                    <div class="small text-body-secondary">{{ \Illuminate\Support\Str::limit($n->data['body'] ?? '', 110) }}</div>
                    <div class="small text-body-tertiary">{{ $n->created_at->diffForHumans() }}</div>
                </a>
            @empty
                <div class="empty-state py-4"><i class="bi bi-bell-slash"></i>{{ __('No notifications') }}</div>
            @endforelse
        </div>
    </div>
</div>
