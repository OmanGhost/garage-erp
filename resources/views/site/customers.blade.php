@php
    $locale = app()->getLocale();
    $seo = ['title' => __('site_pages.customers.title').' · '.\App\Support\SiteSettings::get('site_name_'.$locale), 'description' => __('site_pages.customers.lead'),
        'canonical' => $alternates[$locale], 'alternates' => $alternates];
    $quotes = collect($clients)->filter(fn ($c) => $c['quote'] !== '');
@endphp
@extends('layouts.site')

@section('content')
@include('site.partials.page-head', ['title' => __('site_pages.customers.title'), 'lead' => __('site_pages.customers.lead'), 'crumbs' => [__('site_pages.nav.customers') => null]])

<section class="site-section pt-4">
    <div class="container">
        @if($clients)
            <div class="site-logos">
                @foreach($clients as $c)
                    <figure class="site-logo-card">
                        @if($c['logo'])
                            <img src="{{ $c['logo'] }}" alt="{{ $c['name'] }}" loading="lazy">
                        @else
                            <span class="site-logo-initials" aria-hidden="true">{{ mb_substr($c['name'], 0, 2) }}</span>
                        @endif
                        <figcaption>
                            @if($c['website'])<a href="{{ $c['website'] }}" rel="noopener nofollow" target="_blank">{{ $c['name'] }}</a>@else<b>{{ $c['name'] }}</b>@endif
                            <small>{{ collect([$c['sector'], $c['city']])->filter()->implode(' · ') }}</small>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
            @if($quotes->isNotEmpty())
                <h2 class="site-h2 mt-5 mb-4">{{ __('site_pages.customers.testimonials') }}</h2>
                <div class="row g-4">
                    @foreach($quotes as $c)
                        <div class="col-md-6 col-lg-4">
                            <blockquote class="site-quote">
                                <i class="bi bi-quote"></i>
                                <p>{{ $c['quote'] }}</p>
                                <footer>{{ $c['person'] ?: $c['name'] }}@if($c['person']) · <span>{{ $c['name'] }}</span>@endif</footer>
                            </blockquote>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <div class="site-empty">
                <i class="bi bi-people"></i>
                <h2>{{ __('site_pages.customers.empty_title') }}</h2>
                <p>{{ __('site_pages.customers.empty_text') }}</p>
                <a class="btn btn-accent btn-lg" href="{{ $locale === 'en' ? route('site.trial.en') : route('site.trial') }}">{{ __('site_pages.common.start_trial') }}</a>
            </div>
        @endif
    </div>
</section>

<section class="site-section site-section-alt">
    <div class="container">
        <h2 class="site-h2 mb-4 text-center">{{ __('site_pages.customers.sectors') }}</h2>
        <div class="row g-3">
            @foreach(__('site_pages.solutions.items') as $key => $s)
                <div class="col-6 col-lg-4">
                    <a class="site-mini-feature" href="{{ \App\Http\Controllers\SitePagesController::url('solutions') }}#{{ $key }}"><i class="bi {{ $s['icon'] }}"></i><span><b>{{ $s['title'] }}</b></span></a>
                </div>
            @endforeach
        </div>
    </div>
</section>

@include('site.partials.cta', ['title' => __('site_pages.customers.join')])
@endsection
