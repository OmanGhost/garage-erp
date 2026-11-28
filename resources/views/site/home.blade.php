@php
    $site = \App\Support\SiteSettings::all();
    $locale = app()->getLocale();
    $siteName = $site['site_name_'.$locale];
    $cur = $locale === 'ar' ? 'ر.ع.' : 'OMR';
    $fmt = fn ($v) => $v === null ? null : rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');
    $trialUrl = $locale === 'en' ? route('site.trial.en') : route('site.trial');
    $canonical = $locale === 'en' ? route('site.home.en') : route('site.home');
    $modules = config('erp.modules');
    $popular = $plans->firstWhere('code', 'professional') ?? $plans->get(intdiv($plans->count(), 2));
    $faq = __('site.faq.items');
    $sameAs = array_values(array_filter([$site['social_x'], $site['social_instagram'], $site['social_linkedin'], $site['social_youtube']]));

    $seo = [
        'canonical' => $canonical,
        'alternates' => ['ar' => route('site.home'), 'en' => route('site.home.en')],
        'schema' => [
            array_filter([
                '@context' => 'https://schema.org', '@type' => 'Organization', '@id' => route('site.home').'#organization',
                'name' => $siteName, 'url' => route('site.home'), 'logo' => asset('icons/icon-512.png'),
                'sameAs' => $sameAs ?: null,
                'contactPoint' => [array_filter([
                    '@type' => 'ContactPoint', 'contactType' => 'sales', 'telephone' => $site['contact_phone'] ?: null,
                    'email' => $site['contact_email'] ?: null, 'areaServed' => 'OM', 'availableLanguage' => ['Arabic', 'English'],
                ])],
            ]),
            [
                '@context' => 'https://schema.org', '@type' => 'WebSite', '@id' => route('site.home').'#website',
                'name' => $siteName, 'url' => $canonical, 'inLanguage' => $locale === 'ar' ? 'ar-OM' : 'en',
                'publisher' => ['@id' => route('site.home').'#organization'],
            ],
            [
                '@context' => 'https://schema.org', '@type' => 'SoftwareApplication',
                'name' => $siteName, 'url' => $canonical, 'applicationCategory' => 'BusinessApplication',
                'applicationSubCategory' => 'Automotive workshop management (ERP)',
                'operatingSystem' => 'Web, Android, iOS, Windows, macOS', 'inLanguage' => ['ar', 'en'],
                'description' => $site['seo_description_'.$locale], 'image' => url($site['og_image']),
                'featureList' => collect(__('site.features.items'))->pluck('title')->all(),
                'offers' => $plans->map(fn ($p) => [
                    '@type' => 'Offer', 'name' => $locale === 'ar' ? ($p->name_ar ?: $p->name) : $p->name,
                    'price' => $fmt($p->price_monthly), 'priceCurrency' => 'OMR', 'url' => $trialUrl.'?plan='.$p->code,
                    'priceSpecification' => ['@type' => 'UnitPriceSpecification', 'price' => $fmt($p->price_monthly), 'priceCurrency' => 'OMR', 'unitCode' => 'MON', 'valueAddedTaxIncluded' => false],
                    'eligibleRegion' => 'OM', 'availability' => 'https://schema.org/InStock',
                ])->values()->all(),
                'publisher' => ['@id' => route('site.home').'#organization'],
            ],
            [
                '@context' => 'https://schema.org', '@type' => 'FAQPage',
                'mainEntity' => collect($faq)->map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->all(),
            ],
        ],
    ];
@endphp
@extends('layouts.site')

@section('content')
{{-- ============================================================ hero --}}
<section class="site-hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="site-badge"><i class="bi bi-geo-alt-fill"></i> {{ __('site.hero.badge') }}</span>
                <h1 class="site-hero-title">{{ __('site.hero.title') }}</h1>
                <p class="site-hero-lead">{{ __('site.hero.lead') }}</p>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <a class="btn btn-accent btn-lg" href="{{ $trialUrl }}">{{ __('site.hero.cta') }} <i class="bi {{ is_rtl() ? 'bi-arrow-left' : 'bi-arrow-right' }}"></i></a>
                    <a class="btn btn-outline-light btn-lg" href="#pricing">{{ __('site.hero.cta2') }}</a>
                </div>
                <p class="site-hero-note"><i class="bi bi-check2-circle"></i> {{ __('site.hero.note') }}</p>
            </div>
            <div class="col-lg-6" aria-hidden="true">
                {{-- product preview built in HTML/CSS (no heavy screenshots) --}}
                <div class="site-mock">
                    <div class="site-mock-bar"><span></span><span></span><span></span><b>{{ __('site.mock.board') }}</b></div>
                    <div class="site-mock-kpis">
                        <div><small>{{ __('site.mock.today') }}</small><strong>1,284.500 <em>{{ $cur }}</em></strong></div>
                        <div><small>{{ __('site.mock.jobs') }}</small><strong>27</strong></div>
                    </div>
                    <div class="site-mock-board">
                        @foreach([['col1', 'info', ['Land Cruiser · 12345 AB', 'Patrol · 9090 H']], ['col2', 'primary', ['Camry · 45678 R', 'Hilux · 7788 KS', 'Sunny · 3321 D']], ['col3', 'success', ['Accent · 5512 M']]] as [$col, $color, $cards])
                            <div class="site-mock-col">
                                <div class="site-mock-col-title"><i class="bi bi-circle-fill text-{{ $color }}"></i> {{ __('site.mock.'.$col) }}</div>
                                @foreach($cards as $i => $card)
                                    <div class="site-mock-card">
                                        <span dir="ltr">{{ $card }}</span>
                                        <div class="site-mock-progress"><i style="width: {{ [35, 70, 100][$loop->parent->index] - $i * 12 }}%"></i></div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="site-mock-toast"><i class="bi bi-check-circle-fill"></i> {{ __('site.mock.approved') }} · JC-2026-000148</div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================================================ stats --}}
<section class="site-stats" aria-label="{{ __('site.nav.features') }}">
    <div class="container">
        <div class="row g-3 text-center">
            @foreach(__('site.stats') as $s)
                <div class="col-6 col-md-3"><div class="site-stat"><strong dir="ltr">{{ $s['value'] }}</strong><span>{{ $s['label'] }}</span></div></div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================================================ features --}}
<section class="site-section" id="features">
    <div class="container">
        <header class="site-section-head">
            <h2>{{ __('site.features.title') }}</h2>
            <p>{{ __('site.features.lead') }}</p>
        </header>
        <div class="row g-4">
            @foreach(__('site.features.items') as $f)
                <div class="col-md-6 col-lg-4">
                    <article class="site-feature">
                        <span class="site-feature-icon"><i class="bi {{ $f['icon'] }}"></i></span>
                        <h3>{{ $f['title'] }}</h3>
                        <p>{{ $f['text'] }}</p>
                    </article>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================================================ flow --}}
<section class="site-section site-section-alt">
    <div class="container">
        <header class="site-section-head"><h2>{{ __('site.flow.title') }}</h2></header>
        <ol class="site-steps">
            @foreach(__('site.flow.steps') as $i => $step)
                <li class="site-step">
                    <span class="site-step-num">{{ $i + 1 }}</span>
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- ============================================================ modules --}}
<section class="site-section" id="modules">
    <div class="container">
        <header class="site-section-head">
            <h2>{{ __('site.modules.title') }}</h2>
            <p>{{ __('site.modules.lead') }}</p>
        </header>
        <div class="row g-3">
            @foreach(__('site_features.items') as $slug => $f)
                <div class="col-sm-6 col-lg-4 col-xl-3">
                    <a class="site-mini-feature" href="{{ \App\Http\Controllers\SitePagesController::url('feature', null, $slug) }}"><i class="bi {{ $f['icon'] }}"></i><span><b>{{ $f['title'] }}</b></span></a>
                </div>
            @endforeach
        </div>
        <div class="text-center mt-4"><a class="btn btn-outline-primary btn-lg" href="{{ \App\Http\Controllers\SitePagesController::url('features') }}">{{ __('site_pages.nav.all_features') }}</a></div>
    </div>
</section>

{{-- ============================================================ Oman + security --}}
<section class="site-section site-section-dark" id="oman">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-7">
                <h2 class="mb-2">{{ __('site.oman.title') }}</h2>
                <p class="site-muted mb-4">{{ __('site.oman.lead') }}</p>
                <ul class="site-checklist">
                    @foreach(__('site.oman.items') as $item)
                        <li><i class="bi bi-check-circle-fill"></i> {{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="col-lg-5">
                <div class="site-security">
                    <h3>{{ __('site.security.title') }}</h3>
                    @foreach(__('site.security.items') as $s)
                        <div class="site-security-item"><i class="bi {{ $s['icon'] }}"></i><span>{{ $s['text'] }}</span></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================================================ solutions --}}
<section class="site-section site-section-alt" id="solutions">
    <div class="container">
        <header class="site-section-head">
            <h2>{{ __('site_pages.solutions.title') }}</h2>
            <p>{{ __('site_pages.solutions.lead') }}</p>
        </header>
        <div class="row g-4">
            @foreach(__('site_pages.solutions.items') as $key => $s)
                <div class="col-md-6 col-lg-4">
                    <a class="site-feature site-feature-link" href="{{ \App\Http\Controllers\SitePagesController::url('solutions') }}#{{ $key }}">
                        <span class="site-feature-icon"><i class="bi {{ $s['icon'] }}"></i></span>
                        <h3>{{ $s['title'] }}</h3><p>{{ $s['text'] }}</p>
                        <span class="site-more">{{ __('site_pages.common.learn_more') }} <i class="bi {{ is_rtl() ? 'bi-arrow-left' : 'bi-arrow-right' }}"></i></span>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================================================ pricing --}}
<section class="site-section" id="pricing">
    <div class="container">
        <header class="site-section-head">
            <h2>{{ __('site.pricing.title') }}</h2>
            <p>{{ __('site.pricing.lead') }}</p>
        </header>
        <div class="site-billing" role="group" aria-label="{{ __('site.pricing.title') }}">
            <button type="button" class="active" data-billing="monthly" aria-pressed="true">{{ __('site.pricing.monthly') }}</button>
            <button type="button" data-billing="yearly" aria-pressed="false">{{ __('site.pricing.yearly') }} <span>{{ __('site.pricing.save') }}</span></button>
        </div>
        <div class="row g-4 justify-content-center">
            @forelse($plans as $plan)
                @php $isPopular = $popular && $popular->id === $plan->id; @endphp
                <div class="col-md-6 col-lg-4">
                    <article class="site-plan {{ $isPopular ? 'is-popular' : '' }}">
                        @if($isPopular)<span class="site-plan-flag">{{ __('site.pricing.popular') }}</span>@endif
                        <h3>{{ $locale === 'ar' ? ($plan->name_ar ?: $plan->name) : $plan->name }}</h3>
                        <div class="site-plan-price" data-price-cycle="monthly">
                            <strong>{{ $fmt($plan->price_monthly) }}</strong> <span>{{ $cur }} {{ __('site.pricing.per_month') }}</span>
                        </div>
                        <div class="site-plan-price" data-price-cycle="yearly" hidden>
                            <strong>{{ $fmt($plan->price_yearly ?: $plan->price_monthly * 12) }}</strong> <span>{{ $cur }} {{ __('site.pricing.per_year') }}</span>
                        </div>
                        @if($plan->trial_days)<p class="site-plan-trial"><i class="bi bi-gift"></i> {{ __('site.pricing.trial_days', ['n' => $plan->trial_days]) }}</p>@endif
                        <ul class="site-plan-limits">
                            @foreach(['users' => $plan->max_users, 'branches' => $plan->max_branches, 'warehouses' => $plan->max_warehouses] as $k => $v)
                                <li><span>{{ __('site.pricing.'.$k) }}</span><b>{{ $v ?? __('site.pricing.unlimited') }}</b></li>
                            @endforeach
                            <li><span>{{ __('site.pricing.storage') }}</span><b>{{ $plan->max_storage_mb ? __('site.pricing.gb', ['n' => $fmt($plan->max_storage_mb / 1024)]) : __('site.pricing.unlimited') }}</b></li>
                        </ul>
                        <p class="site-plan-included">{{ __('site.pricing.included') }}</p>
                        <ul class="site-plan-modules">
                            @foreach(array_values(array_filter((array) $plan->modules, fn ($m) => isset($modules[$m]))) as $m)
                                <li><i class="bi bi-check2"></i> {{ __($modules[$m]['label']) }}</li>
                            @endforeach
                        </ul>
                        <a class="btn {{ $isPopular ? 'btn-accent' : 'btn-outline-primary' }} w-100 mt-auto" data-plan-link href="{{ $trialUrl }}?plan={{ $plan->code }}">{{ __('site.pricing.choose') }}</a>
                    </article>
                </div>
            @empty
                <p class="text-center text-secondary">{{ __('No data') }}</p>
            @endforelse
        </div>
        <p class="site-plan-note">{{ __('site.pricing.vat_note') }} · {{ __('site.pricing.custom') }} <a href="{{ $trialUrl }}">{{ __('site.pricing.contact') }}</a></p>
    </div>
</section>

{{-- ============================================================ FAQ --}}
<section class="site-section site-section-alt" id="faq">
    <div class="container site-narrow">
        <header class="site-section-head"><h2>{{ __('site.faq.title') }}</h2></header>
        <div class="site-faq">
            @foreach($faq as $i => $f)
                <details @if($i === 0) open @endif>
                    <summary><h3>{{ $f['q'] }}</h3></summary>
                    <p>{{ $f['a'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================================================ CTA --}}
<section class="site-cta">
    <div class="container text-center">
        <h2>{{ __('site.cta.title') }}</h2>
        <p>{{ __('site.cta.text') }}</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a class="btn btn-accent btn-lg" href="{{ $trialUrl }}">{{ __('site.cta.button') }}</a>
            @if($wa = preg_replace('/\D/', '', (string) $site['contact_whatsapp']))
                <a class="btn btn-outline-light btn-lg" href="https://wa.me/{{ $wa }}" rel="noopener" target="_blank"><i class="bi bi-whatsapp"></i> {{ __('site.cta.whatsapp') }}</a>
            @endif
        </div>
    </div>
</section>
@endsection
