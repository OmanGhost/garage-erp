@php
    $rtl = is_rtl();
    $reverb = ! $public && config('broadcasting.default') === 'reverb'
        ? ['key' => config('broadcasting.connections.reverb.key'), 'host' => config('broadcasting.connections.reverb.options.host'), 'port' => config('broadcasting.connections.reverb.options.port'), 'scheme' => config('broadcasting.connections.reverb.options.scheme')]
        : null;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ __('Live workshop') }} — {{ $company?->displayName() }}</title>
    <link rel="stylesheet" href="{{ asset($rtl ? 'vendor/bootstrap/bootstrap.rtl.min.css' : 'vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.asset_version', '1') }}">
    <style>
        body { background: #0b1220; color: #e6edf6; overflow-x: hidden; }
        .tv-top { display: flex; align-items: center; gap: 1rem; padding: .8rem 1.4rem; background: #111a2e; border-bottom: 1px solid #1f2b45; }
        .tv-clock { font-size: 2.2rem; font-weight: 800; font-variant-numeric: tabular-nums; }
        .tv-live { display: inline-flex; align-items: center; gap: .4rem; font-size: .85rem; color: #9fb3d1; }
        .tv-live i { color: #22c55e; animation: tvpulse 1.6s infinite; }
        .tv-live.off i { color: #f59e0b; animation: none; }
        @keyframes tvpulse { 50% { opacity: .25; } }
        .tv-wrap { padding: 1rem 1.4rem; }
        .tv-col { background: #111a2e; border: 1px solid #1f2b45; border-radius: 1rem; padding: .8rem; height: 100%; }
        .tv-col h2 { font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; margin: 0 0 .7rem; }
        .tv-col h2 .badge { font-size: .95rem; }
        .tv-card { background: #17223b; border: 1px solid #243352; border-inline-start: 5px solid #3b82f6; border-radius: .75rem; padding: .6rem .75rem; margin-bottom: .55rem; }
        .tv-card.late { border-inline-start-color: #ef4444; background: #2a1620; }
        .tv-card.ready { border-inline-start-color: #22c55e; }
        .tv-card.hold { border-inline-start-color: #f59e0b; }
        .tv-plate { font-size: 1.35rem; font-weight: 800; letter-spacing: .04em; font-family: var(--bs-font-monospace); }
        .tv-sub { color: #9fb3d1; font-size: .9rem; }
        .tv-progress { height: 6px; background: #243352; border-radius: 3px; overflow: hidden; margin-top: .4rem; }
        .tv-progress span { display: block; height: 100%; background: #3b82f6; }
        .tv-kpi { text-align: center; padding: .4rem 1rem; border-radius: .75rem; background: #17223b; min-width: 7rem; }
        .tv-kpi b { display: block; font-size: 1.8rem; line-height: 1.1; }
        .tv-kpi small { color: #9fb3d1; }
        .tv-appt { display: flex; gap: .6rem; align-items: center; padding: .45rem .2rem; border-bottom: 1px solid #1f2b45; }
        .tv-appt time { font-weight: 800; font-size: 1.1rem; min-width: 3.6rem; font-variant-numeric: tabular-nums; }
        .tv-empty { color: #6b7f9e; text-align: center; padding: 1.5rem .5rem; }
        .tv-alert { background: #3a1018; border: 1px solid #7f1d1d; border-radius: .75rem; padding: .5rem .75rem; margin-bottom: .45rem; }
    </style>
</head>
<body>
<div class="tv-top">
    @if($company?->logoUrl())<img src="{{ $company->logoUrl() }}" alt="" style="height:44px">@endif
    <div>
        <div class="fs-4 fw-bold">{{ $company?->displayName() }}</div>
        <div class="tv-live" id="tvLive"><i class="bi bi-circle-fill"></i> <span>{{ $reverb ? __('Live') : __('Auto refresh') }}</span> · <span id="tvUpdated">{{ now()->format('H:i:s') }}</span></div>
    </div>
    <div class="ms-auto text-end">
        <div class="tv-clock" id="tvClock">{{ now()->format('H:i') }}</div>
        <div class="tv-sub">{{ now()->translatedFormat('l j F Y') }}</div>
    </div>
    @unless($public)<a href="{{ route('workshop.planner') }}" class="btn btn-sm btn-outline-light ms-2" title="{{ __('Close') }}" aria-label="{{ __('Close') }}"><i class="bi bi-x-lg"></i></a>@endunless
</div>
<div class="tv-wrap" id="tvBoard">
    @include('workshop::live._board')
</div>

@if($reverb)
    <script src="{{ asset('vendor/echo/pusher.min.js') }}"></script>
    <script src="{{ asset('vendor/echo/echo.iife.js') }}"></script>
@endif
<script>
(function () {
    const url = @json($url), board = document.getElementById('tvBoard'), live = document.getElementById('tvLive'), upd = document.getElementById('tvUpdated');
    const clock = document.getElementById('tvClock');
    setInterval(() => { const d = new Date(); clock.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); }, 5000);
    let busy = false, timer;
    function refresh() {
        if (busy) return; busy = true;
        fetch(url + (url.includes('?') ? '&' : '?') + 'partial=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(r => { if (!r.ok) throw new Error(r.status); return r.text(); })
            .then(html => { board.innerHTML = html; upd.textContent = new Date().toLocaleTimeString(); live.classList.remove('off'); })
            .catch(() => live.classList.add('off'))
            .finally(() => { busy = false; });
    }
    const soon = () => { clearTimeout(timer); timer = setTimeout(refresh, 800); };
    setInterval(refresh, 30000);
    @if($reverb)
    try {
        const cfg = @json($reverb);
        const echo = new window.Echo.default({ broadcaster: 'reverb', key: cfg.key, wsHost: cfg.host, wsPort: cfg.port, wssPort: cfg.port, forceTLS: cfg.scheme === 'https', enabledTransports: ['ws', 'wss'],
            authEndpoint: @json(url('/broadcasting/auth')), auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } } });
        echo.private('company.' + @json($company?->id)).listen('.job-card.status', soon).listen('.workshop.updated', soon);
    } catch (e) { live.classList.add('off'); }
    @endif
    // keep the TV awake where supported
    if (navigator.wakeLock) { navigator.wakeLock.request('screen').catch(() => {}); }
})();
</script>
</body>
</html>
