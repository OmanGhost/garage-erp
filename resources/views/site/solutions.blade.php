@php
    $items = __('site_features.items');
    $u = fn ($s) => \App\Http\Controllers\SitePagesController::url('feature', null, $s);
    $seo = ['title' => __('site_pages.solutions.title').' · '.\App\Support\SiteSettings::get('site_name_'.app()->getLocale()), 'description' => __('site_pages.solutions.lead'),
        'canonical' => $alternates[app()->getLocale()], 'alternates' => $alternates];
@endphp
@extends('layouts.site')

@section('content')
@include('site.partials.page-head', ['title' => __('site_pages.solutions.title'), 'lead' => __('site_pages.solutions.lead'), 'crumbs' => [__('site_pages.nav.solutions') => null]])

<section class="site-section pt-4">
    <div class="container">
        <nav class="site-tabs" aria-label="{{ __('site_pages.nav.solutions') }}">
            @foreach(__('site_pages.solutions.items') as $key => $s)<a href="#{{ $key }}">{{ $s['title'] }}</a>@endforeach
        </nav>
        @foreach(__('site_pages.solutions.items') as $key => $s)
            <article class="site-solution" id="{{ $key }}">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-6">
                        <span class="site-feature-icon"><i class="bi {{ $s['icon'] }}"></i></span>
                        <h2 class="site-h2">{{ $s['title'] }}</h2>
                        <p class="site-prose-lead">{{ $s['text'] }}</p>
                        <ul class="site-checklist site-checklist-dark">
                            @foreach($s['points'] as $p)<li><i class="bi bi-check-circle-fill"></i> {{ $p }}</li>@endforeach
                        </ul>
                    </div>
                    <div class="col-lg-6">
                        <div class="site-solution-features">
                            @foreach($s['features'] as $slug)
                                @continue(! isset($items[$slug]))
                                <a href="{{ $u($slug) }}" class="site-mini-feature">
                                    <i class="bi {{ $items[$slug]['icon'] }}"></i>
                                    <span><b>{{ $items[$slug]['title'] }}</b><small>{{ $items[$slug]['tagline'] }}</small></span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</section>

@include('site.partials.cta')
@endsection
