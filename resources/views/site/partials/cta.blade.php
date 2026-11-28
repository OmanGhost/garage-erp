{{-- Closing call to action used on inner pages --}}
@php $l = app()->getLocale(); @endphp
<section class="site-cta-band">
    <div class="container">
        <div class="site-cta-box">
            <div>
                <h2>{{ $title ?? __('site_pages.features.cta_title') }}</h2>
                <p>{{ $text ?? __('site_pages.features.cta_text') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-accent btn-lg" href="{{ $l === 'en' ? route('site.trial.en') : route('site.trial') }}">{{ __('site_pages.common.start_trial') }}</a>
                <a class="btn btn-outline-light btn-lg" href="{{ \App\Http\Controllers\SitePagesController::url('contact') }}">{{ __('site_pages.common.talk_to_us') }}</a>
            </div>
        </div>
    </div>
</section>
