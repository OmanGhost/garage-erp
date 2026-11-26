<!DOCTYPE html>
@php
    $company = tenant()->company();
    $user = auth()->user();
    $rtl = is_rtl();
@endphp
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-bs-theme="{{ $user?->theme === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $company?->primary_color ?? '#1f6feb' }}">
    <title>@yield('title', __('Dashboard')) · {{ $company?->displayName() ?? config('app.name') }}</title>
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="stylesheet" href="{{ asset($rtl ? 'vendor/bootstrap/bootstrap.rtl.min.css' : 'vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.asset_version', '1') }}">
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}?v={{ config('app.asset_version', '1') }}">
    <style>:root{--brand: {{ $company?->primary_color ?? '#1f6feb' }};}</style>
    @livewireStyles
    @stack('styles')
</head>
<body class="app-body">
<div class="app-shell app-shell-top" id="appShell">
    <div class="app-main">
        <div class="app-header d-print-none">
            @include('partials.topbar')
            @include('partials.topnav')
        </div>
        <main class="app-content container-fluid py-3 px-3 px-lg-4">
            @include('partials.flash')
            @hasSection('page-header')
                @yield('page-header')
            @endif
            @yield('content')
            {{ $slot ?? '' }}
        </main>
        @include('partials.version-footer', ['owner' => $company?->displayName() ?? config('app.name')])
    </div>
</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@livewireScripts
@php($reverbCfg = config('broadcasting.default') === 'reverb' ? ['key' => config('broadcasting.connections.reverb.key'), 'host' => config('broadcasting.connections.reverb.options.host'), 'port' => config('broadcasting.connections.reverb.options.port'), 'scheme' => config('broadcasting.connections.reverb.options.scheme')] : null)
<script>
    window.ERP = {
        locale: @json(app()->getLocale()),
        rtl: @json($rtl),
        currency: @json($company?->currency_code ?? 'OMR'),
        decimals: {{ (int) ($company?->currency_decimals ?? 3) }},
        companyId: @json($company?->id),
        userId: @json($user?->id),
        lookupUrl: @json(url('/lookup')),
        themeUrl: @json(route('profile.theme')),
        reverb: @json($reverbCfg),
        t: {
            confirm: @json(__('Are you sure?')),
            noResults: @json(__('No results')),
            searching: @json(__('Searching...')),
        }
    };
</script>
@if(config('broadcasting.default') === 'reverb')
    <script src="{{ asset('vendor/echo/pusher.min.js') }}"></script>
    <script src="{{ asset('vendor/echo/echo.iife.js') }}"></script>
@endif
<script src="{{ asset('js/ui.js') }}?v={{ config('app.asset_version', '1') }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ config('app.asset_version', '1') }}"></script>
@stack('scripts')
</body>
</html>
