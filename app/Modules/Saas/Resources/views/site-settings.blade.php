@extends('layouts.admin')
@section('title', __('Website & SEO'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0">{{ __('Website & SEO') }}</h4>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('site.home') }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> {{ __('View website') }}</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('site.sitemap') }}" target="_blank" rel="noopener">sitemap.xml</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('site.robots') }}" target="_blank" rel="noopener">robots.txt</a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="post" action="{{ route('admin.site.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-search"></i> {{ __('Search engines (SEO)') }}</div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="indexing_enabled" name="indexing_enabled" value="1" @checked($s['indexing_enabled'] === '1')>
                            <label class="form-check-label" for="indexing_enabled">{{ __('Allow search engines to index the website') }}</label>
                        </div>
                        <div class="form-text">{{ __('Turn off on staging or test servers. Application pages (dashboard, portal, API) are never indexed.') }}</div>
                    </div>
                    @foreach(['ar' => 'العربية', 'en' => 'English'] as $l => $lname)
                        <div class="col-12"><span class="badge text-bg-light border">{{ $lname }}</span></div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Site name') }}</label>
                            <input class="form-control" name="site_name_{{ $l }}" value="{{ old('site_name_'.$l, $s['site_name_'.$l]) }}" required maxlength="120" dir="{{ $l === 'ar' ? 'rtl' : 'ltr' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Page title') }} <small class="text-body-secondary" data-count-for="seo_title_{{ $l }}"></small></label>
                            <input class="form-control" id="seo_title_{{ $l }}" name="seo_title_{{ $l }}" value="{{ old('seo_title_'.$l, $s['seo_title_'.$l]) }}" required maxlength="120" data-limit="60" dir="{{ $l === 'ar' ? 'rtl' : 'ltr' }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Meta description') }} <small class="text-body-secondary" data-count-for="seo_description_{{ $l }}"></small></label>
                            <textarea class="form-control" id="seo_description_{{ $l }}" name="seo_description_{{ $l }}" rows="2" required maxlength="320" data-limit="160" dir="{{ $l === 'ar' ? 'rtl' : 'ltr' }}">{{ old('seo_description_'.$l, $s['seo_description_'.$l]) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Keywords') }}</label>
                            <input class="form-control" name="seo_keywords_{{ $l }}" value="{{ old('seo_keywords_'.$l, $s['seo_keywords_'.$l]) }}" maxlength="300" dir="{{ $l === 'ar' ? 'rtl' : 'ltr' }}">
                            <div class="form-text">{{ __('Comma separated') }}</div>
                        </div>
                    @endforeach
                    <div class="col-12"><hr class="my-1"></div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Google Search Console verification code') }}</label>
                        <input class="form-control" name="google_site_verification" value="{{ old('google_site_verification', $s['google_site_verification']) }}" dir="ltr" placeholder="abc123...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Bing Webmaster verification code') }}</label>
                        <input class="form-control" name="bing_site_verification" value="{{ old('bing_site_verification', $s['bing_site_verification']) }}" dir="ltr">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Google Analytics 4 ID') }}</label>
                        <input class="form-control" name="google_analytics_id" value="{{ old('google_analytics_id', $s['google_analytics_id']) }}" dir="ltr" placeholder="G-XXXXXXXXXX">
                        <div class="form-text">{{ __('Optional. Leave empty to disable analytics.') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Social sharing image (1200×630)') }}</label>
                        <input class="form-control" type="file" name="og_image_file" accept="image/png,image/jpeg,image/webp">
                        <input type="hidden" name="og_image" value="{{ $s['og_image'] }}">
                        <div class="form-text"><a href="{{ url($s['og_image']) }}" target="_blank" rel="noopener">{{ __('Current image') }}</a></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-eye"></i> {{ __('Google preview') }}</div>
                <div class="card-body">
                    <div class="small text-success text-truncate" dir="ltr">{{ route('site.home') }}</div>
                    <div class="fs-5 text-primary text-truncate" id="previewTitle">{{ $s['seo_title_ar'] }}</div>
                    <div class="small text-body-secondary" id="previewDesc">{{ \Illuminate\Support\Str::limit($s['seo_description_ar'], 160) }}</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-telephone"></i> {{ __('Contact details') }}</div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><label class="form-label">{{ __('Email') }}</label><input class="form-control" type="email" name="contact_email" value="{{ old('contact_email', $s['contact_email']) }}" dir="ltr"></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Phone') }}</label><input class="form-control" name="contact_phone" value="{{ old('contact_phone', $s['contact_phone']) }}" dir="ltr"></div>
                    <div class="col-md-6"><label class="form-label">WhatsApp</label><input class="form-control" name="contact_whatsapp" value="{{ old('contact_whatsapp', $s['contact_whatsapp']) }}" dir="ltr" placeholder="96890000000"></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Notify email for new trial requests') }}</label><input class="form-control" type="email" name="trial_notify_email" value="{{ old('trial_notify_email', $s['trial_notify_email']) }}" dir="ltr"></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Address') }} (AR)</label><input class="form-control" name="contact_address_ar" value="{{ old('contact_address_ar', $s['contact_address_ar']) }}" dir="rtl"></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Address') }} (EN)</label><input class="form-control" name="contact_address_en" value="{{ old('contact_address_en', $s['contact_address_en']) }}" dir="ltr"></div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-share"></i> {{ __('Social media') }}</div>
                <div class="card-body row g-3">
                    @foreach(['social_x' => 'X (Twitter)', 'social_instagram' => 'Instagram', 'social_linkedin' => 'LinkedIn', 'social_youtube' => 'YouTube'] as $k => $label)
                        <div class="col-md-6"><label class="form-label">{{ $label }}</label><input class="form-control" type="url" name="{{ $k }}" value="{{ old($k, $s[$k]) }}" dir="ltr" placeholder="https://"></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-people"></i> {{ __('Our customers page') }}</div>
        <div class="card-body">
            <label class="form-label" for="clients">{{ __('One customer per line') }}: <code dir="ltr">{{ __('name | sector | city | logo URL | website | testimonial | person') }}</code></label>
            <textarea class="form-control font-monospace" id="clients" name="clients" rows="6" dir="auto">{{ old('clients', $s['clients']) }}</textarea>
            <div class="form-text">{{ __('Only list real customers who agreed to be shown. When empty, the page invites visitors to start a trial.') }}</div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-file-earmark-text"></i> {{ __('Terms of use & privacy policy') }}</div>
        <div class="card-body row g-3">
            <div class="col-12 form-text mt-0">{{ __('Markdown (## heading, - list, **bold**). Leave empty to use the built-in text. Have the final text reviewed by a lawyer.') }}</div>
            @foreach(['terms_ar' => __('Terms of use').' (AR)', 'terms_en' => __('Terms of use').' (EN)', 'privacy_ar' => __('Privacy policy').' (AR)', 'privacy_en' => __('Privacy policy').' (EN)'] as $k => $label)
                <div class="col-md-6"><label class="form-label" for="{{ $k }}">{{ $label }}</label>
                    <textarea class="form-control" id="{{ $k }}" name="{{ $k }}" rows="8" dir="{{ str_ends_with($k, '_ar') ? 'rtl' : 'ltr' }}" placeholder="{{ __('Built-in text') }}">{{ old($k, $s[$k]) }}</textarea></div>
            @endforeach
        </div>
    </div>
    <div class="text-end"><button class="btn btn-primary px-4">{{ __('Save settings') }}</button></div>
</form>

<script>
    // character counters (recommended: title ≤ 60, description ≤ 160) + live Google preview
    document.querySelectorAll('[data-limit]').forEach(function (el) {
        var out = document.querySelector('[data-count-for="' + el.id + '"]');
        var update = function () {
            var n = el.value.length, limit = +el.dataset.limit;
            out.textContent = '(' + n + ' / ' + limit + ')';
            out.className = n > limit ? 'text-danger' : 'text-body-secondary';
            if (el.id === 'seo_title_ar') document.getElementById('previewTitle').textContent = el.value;
            if (el.id === 'seo_description_ar') document.getElementById('previewDesc').textContent = el.value.slice(0, 160);
        };
        el.addEventListener('input', update); update();
    });
</script>
@endsection
