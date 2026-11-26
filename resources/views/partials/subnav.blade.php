{{-- Section bar: the links of the current menu section (e.g. Warranty & Assets) under the main menu, for quick switching. --}}
@php $section = \App\Support\Menu::section(); @endphp
@if($section)
<nav class="app-subnav d-print-none" aria-label="{{ $section['label'] }}">
    <div class="container-fluid px-3 px-lg-4 d-flex align-items-center gap-2">
        <span class="app-subnav-title text-nowrap">{{ $section['label'] }}</span>
        <div class="app-subnav-links" data-subnav>
            @foreach($section['items'] as $item)
                @php $on = $section['active'] === $item['href']; @endphp
                <a href="{{ $item['href'] }}" class="app-subnav-link {{ $on ? 'active' : '' }}" @if($on) aria-current="page" @endif>
                    <i class="bi {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</nav>
@endif
