<!DOCTYPE html>
@php $company = tenant()->company(); $user = auth()->user(); @endphp
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}" data-bs-theme="{{ $user?->theme === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $company?->primary_color ?? '#1f6feb' }}">
    <title>@yield('title') · {{ $company?->displayName() }}</title>
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="stylesheet" href="{{ asset(is_rtl() ? 'vendor/bootstrap/bootstrap.rtl.min.css' : 'vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.asset_version', '1') }}">
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}?v={{ config('app.asset_version', '1') }}">
    <style>:root{--brand: {{ $company?->primary_color ?? '#1f6feb' }};} body{background:var(--app-bg);padding-bottom:70px} .portal-tabbar{position:fixed;bottom:0;inset-inline:0;background:var(--surface);border-top:1px solid var(--border-soft);display:flex;z-index:1030} .portal-tabbar a{flex:1;text-align:center;padding:.45rem 0;font-size:.72rem;color:var(--bs-secondary-color);text-decoration:none} .portal-tabbar a.active{color:var(--brand)} .portal-tabbar i{font-size:1.25rem;display:block}</style>
    @livewireStyles
</head>
<body>
<nav class="navbar bg-body border-bottom sticky-top"><div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('portal.dashboard') }}">
        @if($company?->logoUrl())<img src="{{ $company->logoUrl() }}" style="height:32px">@else<span class="brand-mark" style="width:32px;height:32px"><i class="bi bi-car-front-fill"></i></span>@endif
        <span class="fw-bold small">{{ $company?->displayName() }}</span></a>
    <div class="d-flex gap-1 align-items-center">
        <form method="post" action="{{ route('locale.switch') }}">@csrf<input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"><button class="btn btn-sm btn-outline-secondary">{{ app()->getLocale() === 'ar' ? 'EN' : 'ع' }}</button></form>
        <div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i></button>
            <ul class="dropdown-menu dropdown-menu-end"><li><span class="dropdown-item-text small">{{ $user->name }}</span></li><li><a class="dropdown-item" href="{{ route('profile.edit') }}">{{ __('Profile & security') }}</a></li><li><a class="dropdown-item" href="{{ route('portal.complaints') }}">{{ __('Complaints & feedback') }}</a></li>
                <li><form method="post" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger">{{ __('Logout') }}</button></form></li></ul></div>
    </div>
</div></nav>
<main class="container py-3">
    @include('partials.flash')
    @yield('content')
</main>
@include('partials.version-footer', ['owner' => $company?->displayName() ?? config('app.name'), 'class' => 'container portal-footer'])
<nav class="portal-tabbar">
    @foreach([['portal.dashboard', 'bi-house', __('Home')], ['portal.vehicles', 'bi-car-front', __('Vehicles')], ['portal.appointments.create', 'bi-calendar-plus', __('Book')], ['portal.invoices', 'bi-receipt', __('Invoices')], ['portal.warranties', 'bi-patch-check', __('Warranty')]] as [$r, $i, $l])
        <a href="{{ route($r) }}" class="{{ request()->routeIs($r) ? 'active' : '' }}"><i class="bi {{ $i }}"></i>{{ $l }}</a>
    @endforeach
</nav>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@livewireScripts
<script>if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});</script>
@stack('scripts')
<script src="{{ asset('js/ui.js') }}?v={{ config('app.asset_version', '1') }}"></script>
</body>
</html>
