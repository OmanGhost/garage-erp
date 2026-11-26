<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}" data-bs-theme="{{ auth()->user()?->theme === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · SaaS Admin</title>
    <link rel="stylesheet" href="{{ asset(is_rtl() ? 'vendor/bootstrap/bootstrap.rtl.min.css' : 'vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.asset_version', '1') }}">
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}?v={{ config('app.asset_version', '1') }}">
    @livewireStyles
</head>
<body class="app-body">
@php($newTrials = \App\Modules\Saas\Models\TrialRequest::query()->where('status', 'new')->count())
@php($newDomains = \Illuminate\Support\Facades\Schema::hasTable('domain_requests') ? \App\Modules\Saas\Models\DomainRequest::query()->where('status', 'new')->count() : 0)
{{-- collapses behind a menu button on phones and tablets --}}
<nav class="navbar navbar-expand-xl bg-dark" data-bs-theme="dark"><div class="container-fluid">
    <a class="navbar-brand" href="{{ route('admin.dashboard') }}"><i class="bi bi-buildings"></i> {{ config('app.name') }} — SaaS</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="{{ __('Toggle menu') }}">
        <span class="navbar-toggler-icon"></span>@if($newTrials + $newDomains)<span class="badge text-bg-warning ms-1">{{ $newTrials + $newDomains }}</span>@endif
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
        <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i> {{ __('Dashboard') }}</a></li>
            <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.clients.*', 'admin.companies.*') ? 'active' : '' }}" href="{{ route('admin.clients.index') }}"><i class="bi bi-people"></i> {{ __('Clients') }}</a></li>
            <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.plans*') ? 'active' : '' }}" href="{{ route('admin.plans') }}"><i class="bi bi-stars"></i> {{ __('Plans') }}</a></li>
            <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.domains.*') ? 'active' : '' }}" href="{{ route('admin.domains.index') }}"><i class="bi bi-globe2"></i> {{ __('Domains & e-mail') }} @if($newDomains)<span class="badge text-bg-warning">{{ $newDomains }}</span>@endif</a></li>
            <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.trials.*') ? 'active' : '' }}" href="{{ route('admin.trials.index') }}"><i class="bi bi-rocket-takeoff"></i> {{ __('Trial requests') }} @if($newTrials)<span class="badge text-bg-warning">{{ $newTrials }}</span>@endif</a></li>
            <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.site.*') ? 'active' : '' }}" href="{{ route('admin.site.edit') }}"><i class="bi bi-window"></i> {{ __('Website & SEO') }}</a></li>
        </ul>
        <div class="d-flex gap-2 py-2 py-xl-0">
            <form method="post" action="{{ route('locale.switch') }}">@csrf<input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"><button class="btn btn-sm btn-outline-light">{{ app()->getLocale() === 'ar' ? 'EN' : 'ع' }}</button></form>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> {{ __('Logout') }}</button></form>
        </div>
    </div>
</div></nav>
<main class="container-fluid py-3 px-3 px-lg-4" style="min-height: calc(100vh - 100px)">@include('partials.flash')@yield('content')</main>
@include('partials.version-footer', ['owner' => config('app.name').' — SaaS', 'class' => 'border-top'])
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@livewireScripts
<script src="{{ asset('js/ui.js') }}?v={{ config('app.asset_version', '1') }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ config('app.asset_version', '1') }}"></script>
</body></html>
