{{-- Footer with the application version (VERSION file) and build; shown on every system page --}}
@php $appVersion = \App\Support\AppVersion::version(); $appBuild = \App\Support\AppVersion::build(); @endphp
<footer class="app-footer d-flex flex-wrap align-items-center gap-2 text-body-secondary small px-3 px-lg-4 py-2 d-print-none {{ $class ?? '' }}">
    <span>© {{ date('Y') }} {{ $owner ?? config('app.name') }}</span>
    <span class="ms-auto d-inline-flex align-items-center gap-1" title="{{ __('Version') }} {{ $appVersion }}@if($appBuild) · build {{ $appBuild }}@endif">
        <i class="bi bi-tag"></i> {{ __('Garage ERP') }} <span class="badge rounded-pill text-bg-light border fw-normal" dir="ltr">v{{ $appVersion }}</span>
        @if($appBuild)<span class="font-monospace opacity-75" dir="ltr">{{ $appBuild }}</span>@endif
    </span>
</footer>
