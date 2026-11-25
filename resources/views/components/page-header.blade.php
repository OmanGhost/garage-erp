@props(['title', 'subtitle' => null, 'breadcrumbs' => [], 'icon' => null])
<div class="page-header d-print-none">
    <div class="me-auto min-w-0">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="bi bi-house"></i></a></li>
                @foreach($breadcrumbs as $label => $url)
                    @if(is_int($label))
                        <li class="breadcrumb-item active">{{ $url }}</li>
                    @else
                        <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                    @endif
                @endforeach
            </ol>
        </nav>
        <h1 class="text-truncate">@if($icon)<i class="bi {{ $icon }} text-brand me-1"></i>@endif{{ $title }}</h1>
        @if($subtitle)<div class="text-body-secondary small">{!! $subtitle !!}</div>@endif
    </div>
    <div class="d-flex flex-wrap gap-2">{{ $slot }}</div>
</div>
