@php
    $cat = __('site_features');
    $u = fn ($slug) => \App\Http\Controllers\SitePagesController::url('feature', null, $slug);
    $seo = ['title' => __('site_pages.features.title').' · '.\App\Support\SiteSettings::get('site_name_'.app()->getLocale()), 'description' => __('site_pages.features.lead'),
        'canonical' => $alternates[app()->getLocale()], 'alternates' => $alternates];
    $byGroup = collect($cat['items'])->groupBy('group', true);
@endphp
@extends('layouts.site')

@section('content')
@include('site.partials.page-head', ['title' => __('site_pages.features.title'), 'lead' => __('site_pages.features.lead'), 'crumbs' => [__('site_pages.nav.features') => null]])

<section class="site-section pt-4">
    <div class="container">
        <nav class="site-tabs" aria-label="{{ __('site_pages.nav.features') }}">
            @foreach($cat['groups'] as $g => $label)<a href="#g-{{ $g }}">{{ $label }}</a>@endforeach
        </nav>
        @foreach($cat['groups'] as $g => $label)
            @continue(! $byGroup->has($g))
            <div class="site-group" id="g-{{ $g }}">
                <h2 class="site-group-title">{{ $label }}</h2>
                <div class="row g-4">
                    @foreach($byGroup[$g] as $slug => $f)
                        <div class="col-md-6 col-xl-4">
                            <a class="site-feature site-feature-link" href="{{ $u($slug) }}">
                                <span class="site-feature-icon"><i class="bi {{ $f['icon'] }}"></i></span>
                                <h3>{{ $f['title'] }}</h3>
                                <p>{{ $f['tagline'] }}</p>
                                <span class="site-more">{{ __('site_pages.common.learn_more') }} <i class="bi {{ is_rtl() ? 'bi-arrow-left' : 'bi-arrow-right' }}"></i></span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>

@include('site.partials.cta')
@endsection
