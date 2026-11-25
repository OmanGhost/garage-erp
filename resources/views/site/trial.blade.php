@php
    $site = \App\Support\SiteSettings::all();
    $locale = app()->getLocale();
    $siteName = $site['site_name_'.$locale];
    $home = $locale === 'en' ? route('site.home.en') : route('site.home');
    $canonical = $locale === 'en' ? route('site.trial.en') : route('site.trial');
    $selected = old('plan_id', optional($plans->firstWhere('code', $selectedPlan))->id);
    $cities = $locale === 'ar'
        ? ['مسقط', 'السيب', 'بوشر', 'مطرح', 'صحار', 'صلالة', 'نزوى', 'صور', 'البريمي', 'عبري', 'إبراء', 'الرستاق', 'بركاء', 'شناص', 'خصب', 'الدقم']
        : ['Muscat', 'Seeb', 'Bawshar', 'Muttrah', 'Sohar', 'Salalah', 'Nizwa', 'Sur', 'Al Buraimi', 'Ibri', 'Ibra', 'Rustaq', 'Barka', 'Shinas', 'Khasab', 'Duqm'];

    $seo = [
        'title' => __('site.trial.seo_title', ['site' => $siteName]),
        'description' => __('site.trial.lead'),
        'canonical' => $canonical,
        'alternates' => ['ar' => route('site.trial'), 'en' => route('site.trial.en')],
        'schema' => [[
            '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => $siteName, 'item' => $home],
                ['@type' => 'ListItem', 'position' => 2, 'name' => __('site.trial.title'), 'item' => $canonical],
            ],
        ]],
    ];
@endphp
@extends('layouts.site')

@section('content')
<section class="site-page-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb site-breadcrumb">
                <li class="breadcrumb-item"><a href="{{ $home }}">{{ $siteName }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ __('site.trial.title') }}</li>
            </ol>
        </nav>
        <h1>{{ __('site.trial.title') }}</h1>
        <p>{{ __('site.trial.lead') }}</p>
    </div>
</section>

<section class="site-section pt-0">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-4 order-lg-2">
                <aside class="site-trial-aside">
                    <ul class="site-checklist">
                        @foreach(__('site.trial.benefits') as $b)
                            <li><i class="bi bi-check-circle-fill"></i> {{ $b }}</li>
                        @endforeach
                    </ul>
                    @if($site['contact_phone'] || $site['contact_email'])
                        <hr>
                        <p class="mb-1 fw-semibold">{{ __('site.footer.contact') }}</p>
                        @if($site['contact_phone'])<p class="mb-1"><i class="bi bi-telephone"></i> <a href="tel:{{ preg_replace('/[^\d+]/', '', $site['contact_phone']) }}" dir="ltr">{{ $site['contact_phone'] }}</a></p>@endif
                        @if($site['contact_email'])<p class="mb-0"><i class="bi bi-envelope"></i> <a href="mailto:{{ $site['contact_email'] }}">{{ $site['contact_email'] }}</a></p>@endif
                    @endif
                </aside>
            </div>
            <div class="col-lg-8">
                <form method="post" action="{{ route('site.trial.store') }}" class="site-form" novalidate>
                    @csrf
                    <input type="hidden" name="locale" value="{{ $locale }}">
                    <input type="hidden" name="_ft" value="{{ $formToken }}">
                    <input type="hidden" name="utm_source" value="{{ request('utm_source') }}">
                    {{-- honeypot: hidden from people, filled in by bots --}}
                    <div class="site-hp" aria-hidden="true"><label for="website">Website</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>

                    @if($errors->any())
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">{{ __('site.form.name') }} *</label>
                            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required maxlength="190" autocomplete="name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="company_name">{{ __('site.form.company_name') }} *</label>
                            <input class="form-control @error('company_name') is-invalid @enderror" id="company_name" name="company_name" value="{{ old('company_name') }}" required maxlength="190" autocomplete="organization">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">{{ __('site.form.email') }} *</label>
                            <input class="form-control @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email" dir="ltr">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">{{ __('site.form.phone') }} *</label>
                            <input class="form-control @error('phone') is-invalid @enderror" type="tel" id="phone" name="phone" value="{{ old('phone') }}" required maxlength="30" autocomplete="tel" dir="ltr" placeholder="+968 9X XXX XXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="business_type">{{ __('site.form.business_type') }} *</label>
                            <select class="form-select @error('business_type') is-invalid @enderror" id="business_type" name="business_type" required>
                                <option value="">—</option>
                                @foreach(__('site.form.types') as $k => $label)
                                    <option value="{{ $k }}" @selected(old('business_type') === $k)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="city">{{ __('site.form.city') }}</label>
                            <input class="form-control" id="city" name="city" value="{{ old('city') }}" list="cities" maxlength="80" autocomplete="address-level2">
                            <datalist id="cities">@foreach($cities as $c)<option value="{{ $c }}">@endforeach</datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="plan_id">{{ __('site.form.plan') }}</label>
                            <select class="form-select" id="plan_id" name="plan_id">
                                <option value="">{{ __('site.form.plan_any') }}</option>
                                @foreach($plans as $p)
                                    <option value="{{ $p->id }}" @selected((string) $selected === (string) $p->id)>{{ $locale === 'ar' ? ($p->name_ar ?: $p->name) : $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="branches">{{ __('site.form.branches') }}</label>
                            <input class="form-control" type="number" id="branches" name="branches" value="{{ old('branches', 1) }}" min="1" max="500" inputmode="numeric">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="message">{{ __('site.form.message') }}</label>
                            <textarea class="form-control" id="message" name="message" rows="4" maxlength="2000">{{ old('message') }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" id="consent" name="consent" value="1" @checked(old('consent')) required>
                                <label class="form-check-label" for="consent">{{ __('site.form.consent') }}</label>
                            </div>
                        </div>
                        <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                            <button class="btn btn-accent btn-lg" type="submit">{{ __('site.trial.submit') }}</button>
                            <small class="text-secondary"><i class="bi bi-lock"></i> {{ __('site.trial.privacy') }}</small>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
