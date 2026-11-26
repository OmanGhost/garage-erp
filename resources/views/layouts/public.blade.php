<!DOCTYPE html>
@php $company = $company ?? tenant()->company(); @endphp
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ $company?->displayName() }}</title>
    <link rel="stylesheet" href="{{ asset(is_rtl() ? 'vendor/bootstrap/bootstrap.rtl.min.css' : 'vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}?v={{ config('app.asset_version', '1') }}">
    <style>:root{--brand: {{ $company?->primary_color ?? '#1f6feb' }};} body{background:var(--app-bg);}</style>
    @livewireStyles
</head>
<body>
<nav class="navbar bg-white border-bottom mb-3"><div class="container">
    <span class="navbar-brand d-flex align-items-center gap-2">
        @if($company?->logoUrl())<img src="{{ $company->logoUrl() }}" style="height:36px">@else<span class="brand-mark"><i class="bi bi-car-front-fill"></i></span>@endif
        <span class="fw-bold">{{ $company?->displayName() }}</span>
    </span>
    <form method="post" action="{{ route('locale.switch') }}">@csrf<input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"><button class="btn btn-sm btn-outline-secondary">{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</button></form>
</div></nav>
<main class="container pb-5">
    @include('partials.flash')
    @yield('content')
</main>
<footer class="text-center text-body-secondary small pb-4">{{ $company?->displayName() }} · {{ $company?->phone }} @if($company?->vat_number)· VATIN {{ $company->vat_number }}@endif</footer>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@livewireScripts
@stack('scripts')
<script src="{{ asset('js/ui.js') }}?v={{ config('app.asset_version', '1') }}"></script>
</body>
</html>
