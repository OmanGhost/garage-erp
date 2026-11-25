{{-- Horizontal main menu (desktop) + slide-in menu (mobile). Groups and permissions come from App\Support\Menu. --}}
@php
    $menu = \App\Support\Menu::visible();
    $current = url()->current();
    $isActive = fn ($href) => $href !== '#' && ($current === $href || str_starts_with($current, rtrim($href, '/').'/'));
    $primary = $menu[0] ?? null;          // "Main": shown as direct links
    $groups = array_slice($menu, 1);      // other sections: dropdowns
@endphp

{{-- desktop menu bar --}}
<nav class="app-topnav d-none d-lg-block" aria-label="{{ __('Main navigation') }}">
    <div class="container-fluid px-3 px-lg-4">
        <ul class="topnav-list" data-topnav>
            @if($primary)
                @foreach($primary['items'] as $item)
                    <li class="topnav-item" data-topnav-item>
                        <a href="{{ $item['href'] }}" class="topnav-link {{ $isActive($item['href']) ? 'active' : '' }}" @if($isActive($item['href'])) aria-current="page" @endif>
                            <i class="bi {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
                <li class="topnav-sep" aria-hidden="true"></li>
            @endif

            @foreach($groups as $gi => $group)
                @php
                    $groupActive = collect($group['items'])->contains(fn ($i) => $isActive($i['href']));
                    $count = count($group['items']);
                @endphp
                <li class="topnav-item dropdown" data-topnav-item>
                    <button class="topnav-link dropdown-toggle {{ $groupActive ? 'active' : '' }}" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span>{{ $group['label'] }}</span>
                    </button>
                    <div class="dropdown-menu topnav-menu shadow {{ $count > 8 ? 'is-wide' : '' }}">
                        <div class="topnav-menu-title">{{ $group['label'] }}</div>
                        <div class="topnav-menu-grid">
                            @foreach($group['items'] as $item)
                                <a href="{{ $item['href'] }}" class="dropdown-item {{ $isActive($item['href']) ? 'active' : '' }}">
                                    <i class="bi {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </li>
            @endforeach

            {{-- sections that don't fit the width are moved here by app.js --}}
            <li class="topnav-item dropdown topnav-more d-none" data-topnav-more>
                <button class="topnav-link dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bi bi-three-dots"></i><span>{{ __('More') }}</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end topnav-menu is-wide shadow" data-topnav-more-menu></div>
            </li>
        </ul>
    </div>
</nav>

{{-- mobile slide-in menu --}}
<div class="offcanvas offcanvas-start app-mobile-menu d-print-none" tabindex="-1" id="appMenu" aria-label="{{ __('Main navigation') }}">
    <div class="offcanvas-header">
        <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none">
            @if($company?->logoUrl())<img src="{{ $company->logoUrl() }}" alt="" class="brand-logo">@else<span class="brand-mark"><i class="bi bi-car-front-fill"></i></span>@endif
            <span class="brand-text text-truncate">{{ $company?->displayName() ?? config('app.name') }}</span>
        </a>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}"></button>
    </div>
    <div class="px-3 pb-2"><input type="search" class="form-control form-control-sm" placeholder="{{ __('Filter menu...') }}" data-erp-menu-filter></div>
    <div class="offcanvas-body pt-0">
        @foreach($menu as $gi => $group)
            @php $open = $gi === 0 || collect($group['items'])->contains(fn ($i) => $isActive($i['href'])); @endphp
            <div class="nav-group" data-menu-group>
                <button class="nav-group-title {{ $open ? '' : 'collapsed' }}" data-bs-toggle="collapse" data-bs-target="#mmenu-g{{ $gi }}" aria-expanded="{{ $open ? 'true' : 'false' }}">
                    <span>{{ $group['label'] }}</span><i class="bi bi-chevron-down small"></i>
                </button>
                <div class="collapse {{ $open ? 'show' : '' }}" id="mmenu-g{{ $gi }}">
                    @foreach($group['items'] as $item)
                        <a href="{{ $item['href'] }}" class="nav-link {{ $isActive($item['href']) ? 'active' : '' }}" data-menu-item>
                            <i class="bi {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
