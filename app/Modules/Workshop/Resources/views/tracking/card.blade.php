@extends('layouts.app')
@section('title', $jc->vehicle?->plate())
@section('content')
@php
    $open = $jc->isOpen();
    $canUpdate = auth()->user()->can('vehicle_tracking.update') && $open;
    $drive = $jc->testDrives->whereIn('status', ['approved', 'out'])->sortByDesc('id')->first();
    $evIcon = ['moved' => 'bi-geo-alt', 'key_out' => 'bi-person-badge', 'key_in' => 'bi-key', 'test_out' => 'bi-signpost-2', 'test_in' => 'bi-flag', 'tagged' => 'bi-tag'];
    $evLabel = ['moved' => __('Moved'), 'key_out' => __('Key handed over'), 'key_in' => __('Key returned'), 'test_out' => __('Test drive started'), 'test_in' => __('Test drive returned'), 'tagged' => __('Key tag')];
@endphp
<x-page-header :title="$jc->vehicle?->plate()" icon="bi-key" :breadcrumbs="[__('Vehicle & key tracking') => route('tracking.index'), $jc->number]"
    :subtitle="e($jc->vehicle?->title()).' · '.e($jc->number).' · '.status_badge($jc->status)">
    <a href="{{ route('job-cards.show', $jc) }}" class="btn btn-outline-secondary"><i class="bi bi-clipboard-check"></i> {{ __('Job card') }}</a>
    <a href="{{ route('tracking.key-tag', $jc) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-qr-code"></i> {{ __('Key tag') }}</a>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="row g-2 mb-3">
            <div class="col-sm-4"><div class="card h-100"><div class="card-body"><div class="small text-body-secondary"><i class="bi bi-geo-alt"></i> {{ __('Location') }}</div><div class="fs-5 fw-bold">{{ $jc->yardLocation?->code ?? '—' }}</div><div class="small">{{ $jc->yardLocation?->name }}</div></div></div></div>
            <div class="col-sm-4"><div class="card h-100 {{ $jc->keyHolder ? 'border-warning' : '' }}"><div class="card-body"><div class="small text-body-secondary"><i class="bi bi-key"></i> {{ __('Key') }}</div>
                @if($jc->keyHolder)<div class="fs-6 fw-bold">{{ $jc->keyHolder->name }}</div><div class="small text-warning">{{ __('With employee') }}</div>
                @elseif($jc->key_hook)<div class="fs-5 fw-bold">{{ __('Hook') }} {{ $jc->key_hook }}</div><div class="small text-success">{{ __('In the key cabinet') }}</div>
                @else<div class="fs-5 fw-bold">—</div>@endif</div></div></div>
            <div class="col-sm-4"><div class="card h-100"><div class="card-body"><div class="small text-body-secondary"><i class="bi bi-tag"></i> {{ __('Key tag') }}</div><div class="fs-5 fw-bold font-monospace">{{ $jc->key_tag ?? '—' }}</div><div class="small">{{ __('QR / RFID') }}</div></div></div></div>
        </div>

        @if($canUpdate)
        <div class="card mb-3"><div class="card-body">
            <div class="row g-3">
                <form method="post" action="{{ route('tracking.action', [$jc, 'move']) }}" class="col-md-6">@csrf<input type="hidden" name="via" value="{{ $via }}">
                    <label class="form-label small fw-semibold" for="t_loc"><i class="bi bi-geo-alt"></i> {{ __('Move vehicle to') }}</label>
                    <div class="input-group"><select id="t_loc" name="yard_location_id" class="form-select" required><option value="">—</option>@foreach($locations as $l)<option value="{{ $l->id }}" @selected($jc->yard_location_id === $l->id)>{{ $l->displayName() }}</option>@endforeach</select><button class="btn btn-primary">{{ __('Save') }}</button></div>
                </form>
                @if($jc->keyHolder || ! $jc->key_hook)
                <form method="post" action="{{ route('tracking.action', [$jc, 'key-in']) }}" class="col-md-6">@csrf<input type="hidden" name="via" value="{{ $via }}">
                    <label class="form-label small fw-semibold" for="t_hook"><i class="bi bi-key"></i> {{ __('Return key to hook') }}</label>
                    <div class="input-group"><input id="t_hook" name="key_hook" class="form-control font-monospace" required maxlength="30" placeholder="{{ __('e.g. A-12') }}" value="{{ $jc->key_hook }}"><button class="btn btn-success">{{ __('Return') }}</button></div>
                </form>
                @endif
                <form method="post" action="{{ route('tracking.action', [$jc, 'key-out']) }}" class="col-md-6">@csrf<input type="hidden" name="via" value="{{ $via }}">
                    <label class="form-label small fw-semibold" for="t_holder"><i class="bi bi-person-badge"></i> {{ __('Hand the key to') }}</label>
                    <div class="input-group"><select id="t_holder" name="holder_id" class="form-select" required><option value="">—</option>@foreach($staff as $id => $n)<option value="{{ $id }}" @selected($jc->key_holder_id === $id) @selected(! $jc->key_holder_id && $id === auth()->id())>{{ $n }}</option>@endforeach</select><button class="btn btn-warning">{{ __('Hand over') }}</button></div>
                </form>
                <form method="post" action="{{ route('tracking.action', [$jc, 'tag']) }}" class="col-md-6">@csrf
                    <label class="form-label small fw-semibold" for="t_tag"><i class="bi bi-broadcast"></i> {{ __('Link an RFID tag (optional)') }}</label>
                    <div class="input-group"><input id="t_tag" name="key_tag" class="form-control font-monospace" maxlength="40" data-scan-target placeholder="{{ __('Scan the RFID tag') }}"><button class="btn btn-outline-secondary">{{ __('Link tag') }}</button></div>
                </form>
            </div>
        </div></div>
        @endif

        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i>{{ __('Movement history') }}</div>
            <ul class="list-group list-group-flush small">
                @forelse($jc->trackingEvents->sortByDesc('id') as $e)
                    <li class="list-group-item d-flex gap-2">
                        <i class="bi {{ $evIcon[$e->type] ?? 'bi-dot' }} text-brand"></i>
                        <div class="flex-grow-1">
                            <b>{{ $evLabel[$e->type] ?? $e->type }}</b>
                            @if($e->type === 'moved') → {{ $e->location?->displayName() }}@endif
                            @if($e->type === 'key_out' || $e->type === 'test_out') → {{ $e->holder?->name }}@endif
                            @if($e->type === 'key_in') → {{ __('Hook') }} {{ $e->key_hook }}@endif
                            @if($e->odometer) · {{ number_format($e->odometer) }} km @endif
                            @if($e->notes)<span class="text-body-secondary"> · {{ $e->notes }}</span>@endif
                            <div class="text-body-secondary">{{ $e->user?->name }} · {{ $e->created_at?->format('Y-m-d H:i') }} @if($e->via !== 'web')<span class="badge text-bg-light">{{ strtoupper($e->via) }}</span>@endif</div>
                        </div>
                    </li>
                @empty<li class="list-group-item text-body-secondary">—</li>@endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3" id="test-drive">
            <div class="card-header"><i class="bi bi-signpost-2 me-1"></i>{{ __('Test drive') }}</div>
            <div class="card-body">
                @if($drive && $drive->status === 'approved')
                    <div class="mb-2"><span class="badge text-bg-secondary">{{ $drive->number }}</span> {{ __('Driver') }}: <b>{{ $drive->driver?->name }}</b>@if($drive->max_km) · {{ __('max :n km', ['n' => $drive->max_km]) }}@endif</div>
                    @can('test_drives.update')
                    <form method="post" action="{{ route('test-drives.action', [$drive, 'start']) }}" class="row g-2 mb-2">@csrf
                        <div class="col-7"><label class="form-label small mb-0" for="td_o">{{ __('Odometer before (km)') }}</label><input id="td_o" type="number" name="odometer" min="0" class="form-control" required value="{{ $jc->vehicle?->odometer ?: $jc->odometer_in }}"></div>
                        <div class="col-5"><label class="form-label small mb-0" for="td_f">{{ __('Fuel %') }}</label><input id="td_f" type="number" name="fuel" min="0" max="100" class="form-control" value="{{ $jc->fuel_level }}"></div>
                        <div class="col-12 d-grid"><button class="btn btn-danger"><i class="bi bi-play-fill"></i> {{ __('Start test drive') }}</button></div>
                    </form>
                    @endcan
                    <div class="d-flex gap-2"><a href="{{ route('test-drives.print', $drive) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print permit') }}</a>
                        @can('test_drives.create')<form method="post" action="{{ route('test-drives.action', [$drive, 'cancel']) }}" data-confirm="{{ __('Cancel this permit?') }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('Cancel') }}</button></form>@endcan</div>
                @elseif($drive && $drive->status === 'out')
                    <div class="alert alert-danger py-2"><i class="bi bi-signpost-2"></i> {{ __('On the road since :t with :d', ['t' => $drive->out_at?->format('H:i'), 'd' => $drive->driver?->name]) }} · {{ number_format($drive->odometer_out) }} km</div>
                    @can('test_drives.update')
                    <form method="post" action="{{ route('test-drives.action', [$drive, 'finish']) }}" class="row g-2">@csrf
                        <div class="col-7"><label class="form-label small mb-0" for="td_i">{{ __('Odometer after (km)') }}</label><input id="td_i" type="number" name="odometer" min="{{ $drive->odometer_out }}" class="form-control" required></div>
                        <div class="col-5"><label class="form-label small mb-0" for="td_fi">{{ __('Fuel %') }}</label><input id="td_fi" type="number" name="fuel" min="0" max="100" class="form-control"></div>
                        <div class="col-12"><input name="notes" class="form-control" placeholder="{{ __('Result / remarks') }}" aria-label="{{ __('Notes') }}"></div>
                        <div class="col-12 d-grid"><button class="btn btn-success"><i class="bi bi-flag"></i> {{ __('Vehicle returned') }}</button></div>
                    </form>
                    @endcan
                @elseif($open)
                    @can('test_drives.create')
                    <form method="post" action="{{ route('test-drives.store', $jc) }}" class="row g-2">@csrf
                        <div class="col-12"><label class="form-label small mb-0" for="td_d">{{ __('Driver') }}</label><select id="td_d" name="driver_id" class="form-select" required>@foreach($staff as $id => $n)<option value="{{ $id }}" @selected($id === auth()->id())>{{ $n }}</option>@endforeach</select></div>
                        <div class="col-8"><label class="form-label small mb-0" for="td_p">{{ __('Purpose') }}</label><input id="td_p" name="purpose" class="form-control" placeholder="{{ __('e.g. check noise after repair') }}"></div>
                        <div class="col-4"><label class="form-label small mb-0" for="td_m">{{ __('Max km') }}</label><input id="td_m" type="number" name="max_km" min="1" max="1000" class="form-control" value="10"></div>
                        <div class="col-12"><input name="route" class="form-control" placeholder="{{ __('Route') }}" aria-label="{{ __('Route') }}"></div>
                        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="customer_consent" value="1" id="td_c"><label class="form-check-label small" for="td_c">{{ __('The customer agreed to the test drive') }}</label></div></div>
                        <div class="col-12 d-grid"><button class="btn btn-primary"><i class="bi bi-file-earmark-check"></i> {{ __('Issue permit') }}</button></div>
                    </form>
                    @endcan
                @endif
            </div>
            @if($jc->testDrives->where('status', 'returned')->isNotEmpty())
                <ul class="list-group list-group-flush small">
                    @foreach($jc->testDrives->where('status', 'returned')->sortByDesc('id') as $t)
                        <li class="list-group-item d-flex gap-2"><span>{{ $t->number }}</span><span>{{ $t->driver?->name }}</span>
                            <span class="ms-auto {{ $t->overLimit() ? 'text-danger fw-bold' : '' }}">{{ $t->distance() }} km</span><a href="{{ route('test-drives.print', $t) }}" target="_blank" rel="noopener" aria-label="{{ __('Print') }}"><i class="bi bi-printer"></i></a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
