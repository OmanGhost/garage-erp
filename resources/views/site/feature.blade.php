@php
    $locale = app()->getLocale();
    $groups = __('site_features.groups');
    $u = fn ($s) => \App\Http\Controllers\SitePagesController::url('feature', null, $s);
    $siteName = \App\Support\SiteSettings::get('site_name_'.$locale);
    $seo = ['title' => $f['title'].' · '.$siteName, 'description' => $f['tagline'].' '.\Illuminate\Support\Str::limit($f['lead'], 120),
        'canonical' => $alternates[$locale], 'alternates' => $alternates,
        'schema' => [[
            '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => __('site_pages.common.home'), 'item' => $locale === 'en' ? route('site.home.en') : route('site.home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => __('site_pages.nav.features'), 'item' => \App\Http\Controllers\SitePagesController::url('features')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $f['title'], 'item' => $alternates[$locale]],
            ],
        ]]];
    $trial = $locale === 'en' ? route('site.trial.en') : route('site.trial');
@endphp
@extends('layouts.site')

@section('content')
@include('site.partials.page-head', [
    'title' => $f['title'], 'lead' => $f['tagline'], 'icon' => $f['icon'],
    'crumbs' => [__('site_pages.nav.features') => \App\Http\Controllers\SitePagesController::url('features'), $groups[$f['group']] ?? '' => null],
    'actions' => '<a class="btn btn-accent btn-lg" href="'.e($trial).'">'.e(__('site_pages.common.start_trial')).'</a><a class="btn btn-outline-light btn-lg" href="'.e(\App\Http\Controllers\SitePagesController::url('contact')).'">'.e(__('site_pages.common.talk_to_us')).'</a>',
])

<section class="site-section">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-7">
                <p class="site-prose-lead">{{ $f['lead'] }}</p>
                <h2 class="site-h2">{{ __('site_pages.common.highlights') }}</h2>
                <ul class="site-checklist site-checklist-dark">
                    @foreach($f['highlights'] as $h)<li><i class="bi bi-check-circle-fill"></i> {{ $h }}</li>@endforeach
                </ul>
            </div>
            <div class="col-lg-5">
                <div class="site-aside">
                    <h2 class="site-h3">{{ __('site_pages.common.benefits') }}</h2>
                    @foreach($f['benefits'] as $b)
                        <div class="site-benefit"><i class="bi bi-lightning-charge-fill"></i><div><b>{{ $b['title'] }}</b><p>{{ $b['text'] }}</p></div></div>
                    @endforeach
                    <hr>
                    <div class="small text-body-secondary mb-2">{{ __('site_pages.common.included_in') }}</div>
                    <div class="d-flex flex-wrap gap-2">
                        @forelse($plans as $p)
                            <a class="site-plan-chip" href="{{ $trial }}?plan={{ $p->code }}">{{ $locale === 'ar' ? ($p->name_ar ?: $p->name) : $p->name }}</a>
                        @empty
                            <span class="site-plan-chip">{{ __('site_pages.common.talk_to_us') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@if($related)
<section class="site-section site-section-alt">
    <div class="container">
        <h2 class="site-h2 mb-4">{{ __('site_pages.common.related') }}</h2>
        <div class="row g-4">
            @foreach($related as $s => $r)
                <div class="col-md-4">
                    <a class="site-feature site-feature-link" href="{{ $u($s) }}">
                        <span class="site-feature-icon"><i class="bi {{ $r['icon'] }}"></i></span>
                        <h3>{{ $r['title'] }}</h3><p>{{ $r['tagline'] }}</p>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@include('site.partials.cta')
@endsection
