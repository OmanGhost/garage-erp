{{-- Header band of an inner site page: breadcrumbs, title, lead and optional actions --}}
<section class="site-page-head">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="site-crumbs">
                <li><a href="{{ app()->getLocale() === 'en' ? route('site.home.en') : route('site.home') }}">{{ __('site_pages.common.home') }}</a></li>
                @foreach($crumbs ?? [] as $label => $href)
                    <li>@if($href)<a href="{{ $href }}">{{ $label }}</a>@else<span aria-current="page">{{ $label }}</span>@endif</li>
                @endforeach
            </ol>
        </nav>
        @isset($icon)<span class="site-page-icon"><i class="bi {{ $icon }}"></i></span>@endisset
        <h1>{{ $title }}</h1>
        @if(! empty($lead))<p class="site-page-lead">{{ $lead }}</p>@endif
        @if(! empty($actions))<div class="d-flex flex-wrap gap-2 mt-4">{!! $actions !!}</div>@endif
    </div>
</section>
