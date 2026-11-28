@php
    $locale = app()->getLocale();
    $groups = __('site_pages.docs.groups');
    $u = fn ($s) => \App\Http\Controllers\SitePagesController::url('doc', null, $s);
    $seo = ['title' => __('site_pages.docs.title').' · '.\App\Support\SiteSettings::get('site_name_'.$locale), 'description' => __('site_pages.docs.lead'),
        'canonical' => $alternates[$locale], 'alternates' => $alternates];
    $byGroup = collect($docs)->groupBy('group', true);
@endphp
@extends('layouts.site')

@section('content')
@include('site.partials.page-head', ['title' => __('site_pages.docs.title'), 'lead' => __('site_pages.docs.lead'), 'crumbs' => [__('site_pages.nav.docs') => null]])

<section class="site-section pt-4">
    <div class="container">
        <div class="site-doc-search mb-4">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-lg" id="docSearch" placeholder="{{ __('site_pages.docs.search') }}" aria-label="{{ __('site_pages.docs.search') }}">
        </div>
        <p class="text-body-secondary d-none" id="docNone">{{ __('site_pages.docs.not_found') }}</p>
        @foreach($groups as $g => $label)
            @continue(! $byGroup->has($g))
            <div class="site-group" data-doc-group>
                <h2 class="site-group-title">{{ $label }}</h2>
                <div class="row g-3">
                    @foreach($byGroup[$g] as $slug => $d)
                        <div class="col-md-6 col-xl-4" data-doc-card data-text="{{ mb_strtolower($d['title'].' '.$d['summary']) }}">
                            <a class="site-feature site-feature-link" href="{{ $u($slug) }}">
                                <span class="site-feature-icon"><i class="bi {{ $d['icon'] }}"></i></span>
                                <h3>{{ $d['title'] }}</h3><p>{{ $d['summary'] }}</p>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>

@include('site.partials.cta', ['title' => __('site_pages.docs.help'), 'text' => __('site_pages.docs.help_text')])
@endsection

@push('scripts')
<script>
(function () {
    var input = document.getElementById('docSearch'), none = document.getElementById('docNone');
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase(), shown = 0;
        document.querySelectorAll('[data-doc-card]').forEach(function (c) { var ok = !q || c.dataset.text.indexOf(q) !== -1; c.hidden = !ok; shown += ok ? 1 : 0; });
        document.querySelectorAll('[data-doc-group]').forEach(function (g) { g.hidden = !g.querySelector('[data-doc-card]:not([hidden])'); });
        none.classList.toggle('d-none', shown > 0);
    });
})();
</script>
@endpush
