@extends('layouts.public')
@section('title', __('Roadside assistance'))
@section('content')
@php
    $msgs = ['nogeo' => __('Your browser cannot share its location.'), 'ok' => __('Thank you — your location was sent.'), 'fail' => __('Could not send the location.'), 'denied' => __('Location permission was refused. Allow it and try again.')];
    $steps = [['new', __('Call received'), $r->created_at], ['assigned', __('Driver assigned'), $r->assigned_at], ['en_route', __('On the way'), $r->en_route_at], ['on_site', __('On site'), $r->arrived_at], ['completed', __('Completed'), $r->completed_at]];
@endphp
<div class="card mx-auto" style="max-width:560px">
    <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-truck-flatbed fs-2 text-primary"></i>
            <div><div class="fw-bold">{{ __('Roadside assistance') }} · {{ status_label($r->service_type) }}</div><div class="small text-body-secondary">{{ $r->number }}</div></div>
            <span class="ms-auto">{!! status_badge($r->status) !!}</span>
        </div>
        @if($driver)<div class="alert alert-info py-2"><i class="bi bi-person-badge"></i> {{ __('Your driver') }}: <b>{{ $driver }}</b></div>@endif

        @if($r->isOpen())
            <div class="border rounded p-3 mb-3 text-center">
                <button type="button" class="btn btn-lg btn-success w-100" id="rsaShare"><i class="bi bi-geo-alt-fill"></i> {{ __('Share my location') }}</button>
                <div class="small mt-2" id="rsaMsg" role="status"></div>
                @if($r->location_at)<div class="small text-body-secondary mt-1">{{ __('Last location received') }}: {{ $r->location_at->diffForHumans() }}</div>@endif
                <noscript><div class="small text-danger mt-2">{{ __('Enable JavaScript to share your location.') }}</div></noscript>
            </div>
            @push('scripts')
            <script>
            (function () {
                var btn = document.getElementById('rsaShare'), msg = document.getElementById('rsaMsg');
                var T = @json($msgs);
                function say(text, ok) { msg.textContent = text; msg.className = 'small mt-2 ' + (ok ? 'text-success' : 'text-danger'); btn.disabled = false; }
                btn.addEventListener('click', function () {
                    if (! navigator.geolocation) { say(T.nogeo, false); return; }
                    btn.disabled = true; msg.textContent = '…';
                    navigator.geolocation.getCurrentPosition(function (p) {
                        fetch(@json(route('roadside.public.location', $token)), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                            body: JSON.stringify({ lat: p.coords.latitude, lng: p.coords.longitude }) })
                            .then(function (r) { return r.json().then(function (j) { say(r.ok ? T.ok : (j.message || T.fail), r.ok); }); })
                            .catch(function () { say(T.fail, false); });
                    }, function () { say(T.denied, false); }, { enableHighAccuracy: true, timeout: 15000 });
                });
            })();
            </script>
            @endpush
        @endif

        <ul class="timeline mb-0">
            @foreach($steps as [$k, $l, $at])
                @continue($r->status === 'cancelled' && ! $at)
                <li class="{{ $at ? '' : 'text-body-tertiary' }}"><b>{{ $l }}</b><div class="small text-body-secondary">{{ $at?->format('Y-m-d H:i') ?? '' }}</div></li>
            @endforeach
            @if($r->status === 'cancelled')<li><b>{{ status_label('cancelled') }}</b></li>@endif
        </ul>
        @if($company?->phone)<a href="tel:{{ $company->phone }}" class="btn btn-outline-primary w-100 mt-3"><i class="bi bi-telephone"></i> {{ __('Call us') }} <span dir="ltr">{{ $company->phone }}</span></a>@endif
    </div>
</div>
@endsection
