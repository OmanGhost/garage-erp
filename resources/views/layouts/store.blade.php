<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ $company->displayName() }}</title>
    <meta name="description" content="{{ __('Genuine and used spare parts') }} — {{ $company->displayName() }}">
    <link rel="stylesheet" href="{{ asset(is_rtl() ? 'vendor/bootstrap/bootstrap.rtl.min.css' : 'vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>:root{--brand: {{ $company->primary_color ?? '#1f6feb' }};} body{background:#f6f7fb} .product-card img{height:150px;object-fit:contain;background:#fff}</style>
    @livewireStyles
</head>
<body>
<nav class="navbar navbar-expand bg-white border-bottom sticky-top"><div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('store.home', $company) }}">
        @if($company->logoUrl())<img src="{{ $company->logoUrl() }}" style="height:36px">@else<span class="brand-mark"><i class="bi bi-gear-wide-connected"></i></span>@endif
        <b>{{ $company->displayName() }}</b></a>
    <div class="ms-auto d-flex gap-2 align-items-center">
        <form method="post" action="{{ route('locale.switch') }}">@csrf<input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"><button class="btn btn-sm btn-outline-secondary">{{ app()->getLocale() === 'ar' ? 'EN' : 'ع' }}</button></form>
        <a href="{{ route('store.cart', $company) }}" class="btn btn-primary position-relative"><i class="bi bi-cart3"></i> {{ __('Cart') }}@if(($cartCount ?? 0) > 0)<span class="badge text-bg-light ms-1">{{ $cartCount }}</span>@endif</a>
    </div>
</div></nav>
<main class="container py-4">@include('partials.flash')@yield('content')</main>
<footer class="bg-white border-top py-3 text-center small text-body-secondary">{{ $company->displayName() }} · {{ $company->phone }} · {{ $company->address }} {{ $company->city }}</footer>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@livewireScripts
</body></html>
