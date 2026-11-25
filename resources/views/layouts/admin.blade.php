<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}" data-bs-theme="{{ auth()->user()?->theme === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · SaaS Admin</title>
    <link rel="stylesheet" href="{{ asset(is_rtl() ? 'vendor/bootstrap/bootstrap.rtl.min.css' : 'vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @livewireStyles
</head>
<body class="app-body">
<nav class="navbar navbar-expand bg-dark" data-bs-theme="dark"><div class="container-fluid">
    <a class="navbar-brand" href="{{ route('admin.dashboard') }}"><i class="bi bi-buildings"></i> {{ config('app.name') }} — SaaS</a>
    <ul class="navbar-nav me-auto"><li class="nav-item"><a class="nav-link" href="{{ route('admin.dashboard') }}">{{ __('Tenants') }}</a></li><li class="nav-item"><a class="nav-link" href="{{ route('admin.plans') }}">{{ __('Plans') }}</a></li>
        @php($newTrials = \App\Modules\Saas\Models\TrialRequest::query()->where('status', 'new')->count())
        <li class="nav-item"><a class="nav-link" href="{{ route('admin.trials.index') }}">{{ __('Trial requests') }} @if($newTrials)<span class="badge text-bg-warning">{{ $newTrials }}</span>@endif</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('admin.site.edit') }}">{{ __('Website & SEO') }}</a></li></ul>
    <div class="d-flex gap-2">
        <form method="post" action="{{ route('locale.switch') }}">@csrf<input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"><button class="btn btn-sm btn-outline-light">{{ app()->getLocale() === 'ar' ? 'EN' : 'ع' }}</button></form>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-outline-light">{{ __('Logout') }}</button></form>
    </div>
</div></nav>
<main class="container-fluid py-3 px-4">@include('partials.flash')@yield('content')</main>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@livewireScripts
<script src="{{ asset('js/app.js') }}"></script>
</body></html>
