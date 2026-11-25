@php
    $home = app()->getLocale() === 'en' ? route('site.home.en') : route('site.home');
    $seo = ['title' => __('site.thanks.title'), 'index' => false];
@endphp
@extends('layouts.site')

@section('content')
<section class="site-section site-thanks">
    <div class="container text-center site-narrow">
        <span class="site-thanks-icon"><i class="bi bi-check2-circle"></i></span>
        <h1>{{ __('site.thanks.title') }}</h1>
        <p class="lead text-secondary">{{ __('site.thanks.text') }}</p>
        <a class="btn btn-accent btn-lg mt-3" href="{{ $home }}">{{ __('site.thanks.back') }}</a>
    </div>
</section>
@endsection
