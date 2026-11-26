@php
    /** @var array $seo  title, description, canonical, alternates[ar,en], robots, schema[], image */
    $site = \App\Support\SiteSettings::all();
    $locale = app()->getLocale();
    $siteName = $site['site_name_'.$locale] ?? $site['site_name_en'];
    $title = $seo['title'] ?? $site['seo_title_'.$locale];
    $description = $seo['description'] ?? $site['seo_description_'.$locale];
    $indexable = ($site['indexing_enabled'] ?? '1') === '1' && ($seo['index'] ?? true);
    $image = $seo['image'] ?? $site['og_image'];
    $image = str_starts_with($image, 'http') ? $image : url($image);
    $alternates = $seo['alternates'] ?? [];
    $ga = preg_match('/^G-[A-Z0-9]{4,20}$/', (string) $site['google_analytics_id']) ? $site['google_analytics_id'] : null;
    $other = __('site.nav.language_code');
    $switchUrl = $alternates[$other] ?? ($other === 'en' ? route('site.home.en') : route('site.home'));
    $home = $locale === 'en' ? route('site.home.en') : route('site.home');
    $trialUrl = $locale === 'en' ? route('site.trial.en') : route('site.trial');
    $wa = preg_replace('/\D/', '', (string) $site['contact_whatsapp']);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale === 'ar' ? 'ar-OM' : 'en' }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    @if($site['seo_keywords_'.$locale] ?? false)<meta name="keywords" content="{{ $site['seo_keywords_'.$locale] }}">@endif
    <meta name="robots" content="{{ $indexable ? 'index, follow, max-image-preview:large, max-snippet:-1' : 'noindex, nofollow' }}">
    @if(! empty($seo['canonical']))<link rel="canonical" href="{{ $seo['canonical'] }}">@endif
    @foreach($alternates as $lang => $href)
        <link rel="alternate" hreflang="{{ $lang === 'ar' ? 'ar-OM' : 'en' }}" href="{{ $href }}">
    @endforeach
    @if(isset($alternates['ar']))<link rel="alternate" hreflang="x-default" href="{{ $alternates['ar'] }}">@endif

    {{-- Open Graph / social sharing --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $seo['canonical'] ?? url()->current() }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $siteName }}">
    <meta property="og:locale" content="{{ $locale === 'ar' ? 'ar_OM' : 'en_US' }}">
    <meta property="og:locale:alternate" content="{{ $locale === 'ar' ? 'en_US' : 'ar_OM' }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">
    @if($site['social_x'])<meta name="twitter:site" content="{{ '@'.ltrim(basename(parse_url($site['social_x'], PHP_URL_PATH) ?? ''), '@') }}">@endif

    {{-- search engine verification --}}
    @if($site['google_site_verification'])<meta name="google-site-verification" content="{{ $site['google_site_verification'] }}">@endif
    @if($site['bing_site_verification'])<meta name="msvalidate.01" content="{{ $site['bing_site_verification'] }}">@endif

    <meta name="theme-color" content="#0b1b33">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" href="{{ asset('images/logo.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="sitemap" type="application/xml" href="{{ route('site.sitemap') }}">

    <link rel="preload" href="{{ asset('vendor/fonts/cairo/cairo-arabic-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset(is_rtl() ? 'vendor/bootstrap/bootstrap.rtl.min.css' : 'vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ config('app.asset_version', '1') }}">

    {{-- structured data (schema.org) --}}
    @foreach($seo['schema'] ?? [] as $schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach

    @if($ga && $indexable)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $ga }}',{anonymize_ip:true});</script>
    @endif
</head>
<body class="site">
<a class="visually-hidden-focusable site-skip" href="#main">{{ $locale === 'ar' ? 'تخطَّ إلى المحتوى' : 'Skip to content' }}</a>

<header class="site-header" id="top">
    <nav class="navbar navbar-expand-lg" aria-label="{{ __('Main navigation') }}">
        <div class="container">
            <a class="navbar-brand site-brand" href="{{ $home }}">
                <img src="{{ asset('images/logo.svg') }}" alt="" width="36" height="36">
                <span>{{ $siteName }}</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="{{ __('site.nav.menu') }}">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="siteNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="{{ $home }}#features">{{ __('site.nav.features') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ $home }}#modules">{{ __('site.nav.modules') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ $home }}#oman">{{ __('site.nav.oman') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ $home }}#pricing">{{ __('site.nav.pricing') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ $home }}#faq">{{ __('site.nav.faq') }}</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a class="btn btn-link site-lang" href="{{ $switchUrl }}" hreflang="{{ $other }}" lang="{{ $other }}">{{ __('site.nav.language') }}</a>
                    <a class="btn btn-outline-light" href="{{ route('login') }}" rel="nofollow">{{ __('site.nav.login') }}</a>
                    <a class="btn btn-accent" href="{{ $trialUrl }}">{{ __('site.nav.trial') }}</a>
                </div>
            </div>
        </div>
    </nav>
</header>

<main id="main">
    @yield('content')
</main>

<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <a class="site-brand mb-3 d-inline-flex" href="{{ $home }}"><img src="{{ asset('images/logo.svg') }}" alt="" width="32" height="32"><span>{{ $siteName }}</span></a>
                <p class="text-secondary-light mb-3">{{ __('site.footer.about') }}</p>
                <div class="d-flex gap-2">
                    @foreach(['social_x' => 'bi-twitter-x', 'social_instagram' => 'bi-instagram', 'social_linkedin' => 'bi-linkedin', 'social_youtube' => 'bi-youtube'] as $key => $icon)
                        @if($site[$key])<a class="site-social" href="{{ $site[$key] }}" rel="noopener me" target="_blank" aria-label="{{ ucfirst(str_replace('social_', '', $key)) }}"><i class="bi {{ $icon }}"></i></a>@endif
                    @endforeach
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <h2 class="site-footer-title">{{ __('site.footer.product') }}</h2>
                <ul class="list-unstyled site-footer-links">
                    <li><a href="{{ $home }}#features">{{ __('site.nav.features') }}</a></li>
                    <li><a href="{{ $home }}#pricing">{{ __('site.nav.pricing') }}</a></li>
                    <li><a href="{{ $home }}#faq">{{ __('site.nav.faq') }}</a></li>
                    <li><a href="{{ $trialUrl }}">{{ __('site.nav.trial') }}</a></li>
                    <li><a href="{{ route('login') }}" rel="nofollow">{{ __('site.nav.login') }}</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-4">
                <h2 class="site-footer-title">{{ __('site.footer.contact') }}</h2>
                <ul class="list-unstyled site-footer-links">
                    @if($site['contact_phone'])<li><i class="bi bi-telephone"></i> <a href="tel:{{ preg_replace('/[^\d+]/', '', $site['contact_phone']) }}" dir="ltr">{{ $site['contact_phone'] }}</a></li>@endif
                    @if($wa)<li><i class="bi bi-whatsapp"></i> <a href="https://wa.me/{{ $wa }}" rel="noopener" target="_blank">{{ __('site.cta.whatsapp') }}</a></li>@endif
                    @if($site['contact_email'])<li><i class="bi bi-envelope"></i> <a href="mailto:{{ $site['contact_email'] }}">{{ $site['contact_email'] }}</a></li>@endif
                    @if($site['contact_address_'.$locale])<li><i class="bi bi-geo-alt"></i> {{ $site['contact_address_'.$locale] }}</li>@endif
                </ul>
            </div>
        </div>
        <div class="site-footer-bottom">© {{ date('Y') }} {{ $siteName }}. {{ __('site.footer.rights') }}</div>
    </div>
</footer>

@if($wa)
    <a class="site-wa-float" href="https://wa.me/{{ $wa }}" rel="noopener" target="_blank" aria-label="{{ __('site.cta.whatsapp') }}"><i class="bi bi-whatsapp"></i></a>
@endif

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}" defer></script>
<script>
    // shrink header on scroll; monthly/yearly pricing toggle
    (function () {
        var header = document.querySelector('.site-header');
        var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 24); };
        window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
        document.querySelectorAll('[data-billing]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cycle = btn.getAttribute('data-billing');
                document.querySelectorAll('[data-billing]').forEach(function (b) { b.classList.toggle('active', b === btn); b.setAttribute('aria-pressed', b === btn); });
                document.querySelectorAll('[data-price-cycle]').forEach(function (el) { el.hidden = el.getAttribute('data-price-cycle') !== cycle; });
                document.querySelectorAll('[data-plan-link]').forEach(function (a) { var u = new URL(a.href); u.searchParams.set('cycle', cycle); a.href = u.toString(); });
            });
        });
    })();
</script>
</body>
</html>
