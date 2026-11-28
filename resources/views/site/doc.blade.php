@php
    $locale = app()->getLocale();
    $groups = __('site_pages.docs.groups');
    $u = fn ($s) => \App\Http\Controllers\SitePagesController::url('doc', null, $s);
    $seo = ['title' => $doc['title'].' · '.__('site_pages.docs.title'), 'description' => $doc['summary'], 'canonical' => $alternates[$locale], 'alternates' => $alternates,
        'schema' => [['@context' => 'https://schema.org', '@type' => 'TechArticle', 'headline' => $doc['title'], 'description' => $doc['summary'],
            'inLanguage' => $locale === 'ar' ? 'ar-OM' : 'en', 'dateModified' => $updated->toAtomString(), 'url' => $alternates[$locale]]]];
    $byGroup = collect($docs)->groupBy('group', true);
@endphp
@extends('layouts.site')

@section('content')
@include('site.partials.page-head', ['title' => $doc['title'], 'lead' => $doc['summary'], 'icon' => $doc['icon'],
    'crumbs' => [__('site_pages.nav.docs') => \App\Http\Controllers\SitePagesController::url('docs'), $groups[$doc['group']] ?? '' => null]])

<section class="site-section pt-4">
    <div class="container">
        <div class="row g-5">
            <aside class="col-lg-3 order-lg-0 order-1">
                <nav class="site-doc-sidebar" aria-label="{{ __('site_pages.docs.title') }}">
                    @foreach($groups as $g => $label)
                        @continue(! $byGroup->has($g))
                        <div class="site-doc-sidebar-group">{{ $label }}</div>
                        <ul class="list-unstyled site-doc-nav">
                            @foreach($byGroup[$g] as $s => $d)
                                <li><a href="{{ $u($s) }}" class="{{ $s === $slug ? 'active' : '' }}" @if($s === $slug) aria-current="page" @endif>{{ $d['title'] }}</a></li>
                            @endforeach
                        </ul>
                    @endforeach
                </nav>
            </aside>
            <div class="col-lg-6 order-lg-1 order-0">
                <article class="site-prose">{!! $html !!}</article>
                <div class="small text-body-secondary mt-4">{{ __('site_pages.common.updated') }}: {{ $updated->format('Y-m-d') }}</div>
                <nav class="site-doc-pager" aria-label="pager">
                    @if($prev)<a href="{{ $u($prev) }}"><small>{{ __('site_pages.docs.prev') }}</small><b>{{ $docs[$prev]['title'] }}</b></a>@else<span></span>@endif
                    @if($next)<a href="{{ $u($next) }}" class="text-end"><small>{{ __('site_pages.docs.next') }}</small><b>{{ $docs[$next]['title'] }}</b></a>@endif
                </nav>
            </div>
            @if($toc)
                <aside class="col-lg-3 order-2 d-none d-lg-block">
                    <div class="site-doc-toc">
                        <div class="site-doc-sidebar-group">{{ __('site_pages.common.on_this_page') }}</div>
                        <ul class="list-unstyled">@foreach($toc as $t)<li><a href="#{{ $t['id'] }}">{{ $t['text'] }}</a></li>@endforeach</ul>
                    </div>
                </aside>
            @endif
        </div>
    </div>
</section>
@endsection
