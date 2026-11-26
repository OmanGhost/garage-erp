<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f1b2d">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="stylesheet" href="{{ asset(is_rtl() ? 'vendor/bootstrap/bootstrap.rtl.min.css' : 'vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.asset_version', '1') }}">
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}?v={{ config('app.asset_version', '1') }}">
    <style>
        body { min-height: 100vh; background: radial-gradient(circle at 20% 20%, #1f6feb33, transparent 40%), radial-gradient(circle at 80% 80%, #20c99733, transparent 40%), #0f1b2d; }
        .auth-card { max-width: 420px; width: 100%; border: 0; border-radius: 1rem; box-shadow: 0 20px 60px rgba(0,0,0,.35); }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
<div class="auth-card card">
    <div class="card-body p-4">
        <div class="text-center mb-3">
            @isset($domainCompany)
                {{-- the client's own domain: its name and logo --}}
                @if($domainCompany->logoUrl())<img src="{{ $domainCompany->logoUrl() }}" alt="{{ $domainCompany->displayName() }}" class="mb-2" style="max-height:64px;max-width:200px">@else<span class="brand-mark mb-2" style="width:56px;height:56px;font-size:1.8rem"><i class="bi bi-car-front-fill"></i></span>@endif
                <h1 class="h4 fw-bold mb-0">{{ $domainCompany->displayName() }}</h1>
                <div class="text-body-secondary small">{{ __('Workshop & Spare Parts ERP') }}</div>
            @else
            <span class="brand-mark mb-2" style="width:56px;height:56px;font-size:1.8rem"><i class="bi bi-car-front-fill"></i></span>
            <h1 class="h4 fw-bold mb-0">{{ config('app.name') }}</h1>
            <div class="text-body-secondary small">{{ __('Workshop & Spare Parts ERP') }}</div>
            @endisset
        </div>
        @include('partials.flash')
        @yield('content')
        <form method="post" action="{{ route('locale.switch') }}" class="text-center mt-3">@csrf<input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"><button class="btn btn-link btn-sm">{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</button></form>
        <div class="text-center text-body-tertiary small" dir="ltr">v{{ \App\Support\AppVersion::version() }}</div>
    </div>
</div>
<script>if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});</script>
<script src="{{ asset('js/ui.js') }}?v={{ config('app.asset_version', '1') }}"></script>
</body>
</html>
