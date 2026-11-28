@php
    $locale = app()->getLocale();
    $site = \App\Support\SiteSettings::all();
    $wa = preg_replace('/\D/', '', (string) $site['contact_whatsapp']);
    $seo = ['title' => __('site_pages.contact.title').' · '.$site['site_name_'.$locale], 'description' => __('site_pages.contact.lead'),
        'canonical' => $alternates[$locale], 'alternates' => $alternates];
    $t = 'site_pages.contact.';
@endphp
@extends('layouts.site')

@section('content')
@include('site.partials.page-head', ['title' => __($t.'title'), 'lead' => __($t.'lead'), 'crumbs' => [__('site_pages.nav.contact') => null]])

<section class="site-section pt-4">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-7">
                @if($sent)
                    <div class="site-empty" role="status">
                        <i class="bi bi-check2-circle text-success"></i>
                        <h2>{{ __($t.'sent_title') }}</h2>
                        <p>{{ __($t.'sent_text') }}</p>
                    </div>
                @else
                    <form method="post" action="{{ route('site.contact.store') }}" class="site-form" novalidate>@csrf
                        <input type="hidden" name="locale" value="{{ $locale }}">
                        <div class="site-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="c-name">{{ __($t.'name') }} *</label>
                                <input id="c-name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label" for="c-company">{{ __($t.'company') }}</label>
                                <input id="c-company" name="company" class="form-control" value="{{ old('company') }}" maxlength="160" autocomplete="organization"></div>
                            <div class="col-md-6"><label class="form-label" for="c-email">{{ __($t.'email') }} *</label>
                                <input id="c-email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required maxlength="160" autocomplete="email" dir="ltr">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label" for="c-phone">{{ __($t.'phone') }}</label>
                                <input id="c-phone" type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" dir="ltr">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-12"><label class="form-label" for="c-topic">{{ __($t.'topic') }}</label>
                                <select id="c-topic" name="topic" class="form-select">@foreach(__($t.'topics') as $k => $l)<option value="{{ $k }}" @selected(old('topic', request('topic')) === $k)>{{ $l }}</option>@endforeach</select></div>
                            <div class="col-12"><label class="form-label" for="c-msg">{{ __($t.'message') }} *</label>
                                <textarea id="c-msg" name="message" rows="6" class="form-control @error('message') is-invalid @enderror" required minlength="10" maxlength="5000">{{ old('message') }}</textarea>
                                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                                <button class="btn btn-accent btn-lg"><i class="bi bi-send"></i> {{ __($t.'send') }}</button>
                                <small class="text-body-secondary"><a href="{{ \App\Http\Controllers\SitePagesController::url('privacy') }}">{{ __($t.'privacy_note') }}</a></small>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
            <div class="col-lg-5">
                <div class="site-aside">
                    <h2 class="site-h3">{{ __($t.'direct') }}</h2>
                    <ul class="list-unstyled site-contact-list">
                        @if($site['contact_phone'])<li><i class="bi bi-telephone"></i> <a href="tel:{{ preg_replace('/[^\d+]/', '', $site['contact_phone']) }}" dir="ltr">{{ $site['contact_phone'] }}</a></li>@endif
                        @if($wa)<li><i class="bi bi-whatsapp"></i> <a href="https://wa.me/{{ $wa }}" rel="noopener" target="_blank">{{ __('site.cta.whatsapp') }}</a></li>@endif
                        @if($site['contact_email'])<li><i class="bi bi-envelope"></i> <a href="mailto:{{ $site['contact_email'] }}">{{ $site['contact_email'] }}</a></li>@endif
                        @if($site['contact_address_'.$locale])<li><i class="bi bi-geo-alt"></i> {{ $site['contact_address_'.$locale] }}</li>@endif
                    </ul>
                    <hr>
                    <a class="btn btn-outline-primary w-100" href="{{ \App\Http\Controllers\SitePagesController::url('docs') }}"><i class="bi bi-book"></i> {{ __('site_pages.docs.title') }}</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
