@php
    $locale = app()->getLocale();
    $title = __('site_pages.legal.'.$doc.'_title');
    $seo = ['title' => $title.' · '.\App\Support\SiteSettings::get('site_name_'.$locale), 'description' => $title, 'canonical' => $alternates[$locale], 'alternates' => $alternates];
@endphp
@extends('layouts.site')

@section('content')
@include('site.partials.page-head', ['title' => $title, 'lead' => $updated ? __('site_pages.common.updated').': '.$updated : null, 'crumbs' => [$title => null]])

<section class="site-section pt-4">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                <article class="site-prose">{!! $html !!}</article>
            </div>
            <div class="col-lg-4">
                <div class="site-aside">
                    <ul class="list-unstyled site-doc-nav mb-0">
                        <li><a class="{{ $doc === 'terms' ? 'active' : '' }}" href="{{ \App\Http\Controllers\SitePagesController::url('terms') }}">{{ __('site_pages.nav.terms') }}</a></li>
                        <li><a class="{{ $doc === 'privacy' ? 'active' : '' }}" href="{{ \App\Http\Controllers\SitePagesController::url('privacy') }}">{{ __('site_pages.nav.privacy') }}</a></li>
                        <li><a href="{{ \App\Http\Controllers\SitePagesController::url('contact') }}">{{ __('site_pages.nav.contact') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
