@extends('layouts.app')
@section('title', __('Vehicle & key tracking'))
@section('content')
@php
    $byLocation = $cards->groupBy('yard_location_id');
    $keysOut = $cards->whereNotNull('key_holder_id');
    $noLoc = $byLocation->get('', collect())->merge($byLocation->get(null, collect()));
    $typeIcon = ['parking' => 'bi-p-square', 'bay' => 'bi-wrench-adjustable', 'wash' => 'bi-droplet', 'waiting' => 'bi-hourglass', 'road_test' => 'bi-signpost-2', 'outside' => 'bi-tree'];
@endphp
<x-page-header :title="__('Vehicle & key tracking')" icon="bi-key" :breadcrumbs="[__('Workshop'), __('Vehicle & key tracking')]">
    @can('yard_locations.view')<a href="{{ route('crud.index', 'yard-locations') }}" class="btn btn-outline-secondary"><i class="bi bi-geo-alt"></i> {{ __('Yard locations') }}</a>@endcan
</x-page-header>

<div class="card mb-3">
    <div class="card-body">
        <form method="post" action="{{ route('tracking.scan') }}" class="row g-2 align-items-center">@csrf
            <input type="hidden" name="via" value="rfid">
            <div class="col-md-8">
                <div class="input-group input-group-lg">
                    <span class="input-group-text"><i class="bi bi-qr-code-scan"></i></span>
                    <input name="code" class="form-control" autofocus data-scan-target autocomplete="off" placeholder="{{ __('Scan the key tag (QR / RFID / barcode) or type a plate or job card number') }}" aria-label="{{ __('Key tag') }}">
                </div>
            </div>
            <div class="col-md-4 d-grid"><button class="btn btn-primary btn-lg"><i class="bi bi-search"></i> {{ __('Find') }}</button></div>
            <div class="col-12 form-text">{{ __('RFID and barcode readers type the code and press Enter. On a phone, scan the QR on the key tag with the camera to open the vehicle directly.') }}</div>
        </form>
    </div>
</div>

<div class="row g-2 mb-3">
    @foreach([[__('Vehicles in the workshop'), $cards->count(), 'bi-car-front', 'primary'], [__('Without a location'), $noLoc->count(), 'bi-geo', 'warning'], [__('Keys with employees'), $keysOut->count(), 'bi-person-badge', 'info'], [__('On a test drive'), $drives->where('status', 'out')->count(), 'bi-signpost-2', 'danger']] as [$l, $v, $icon, $c])
        <div class="col-6 col-lg-3"><div class="card kpi h-100"><div class="card-body d-flex align-items-center gap-3">
            <span class="kpi-icon bg-{{ $c }}-subtle text-{{ $c }}"><i class="bi {{ $icon }}"></i></span>
            <div><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-4">{{ $v }}</div></div>
        </div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-map me-1"></i>{{ __('Yard map') }}</div>
            <div class="card-body">
                @if($locations->isEmpty())
                    <div class="empty-state py-3"><i class="bi bi-geo-alt"></i>{{ __('Add the parking spots and areas of your yard to see where every vehicle is.') }}
                        @can('yard_locations.create')<div><a class="btn btn-sm btn-primary mt-2" href="{{ route('crud.create', 'yard-locations') }}">{{ __('Add a location') }}</a></div>@endcan</div>
                @else
                    <div class="yard-grid">
                        @foreach($locations as $loc)
                            @php $here = $byLocation->get($loc->id, collect()); @endphp
                            <div class="yard-spot {{ $here->isNotEmpty() ? 'busy' : '' }}">
                                <div class="d-flex align-items-center gap-1 small"><i class="bi {{ $typeIcon[$loc->type] ?? 'bi-geo-alt' }}"></i><b>{{ $loc->code }}</b><span class="text-body-secondary text-truncate">{{ $loc->name }}</span></div>
                                @forelse($here as $jc)
                                    <a href="{{ route('tracking.card', $jc) }}" class="yard-car"><span class="font-monospace fw-bold">{{ $jc->vehicle?->plate() }}</span>
                                        <span class="ms-auto small">@if($jc->key_hook)<i class="bi bi-key"></i> {{ $jc->key_hook }}@elseif($jc->keyHolder)<i class="bi bi-person-badge text-warning"></i>@endif</span></a>
                                @empty
                                    <div class="small text-body-secondary">{{ __('Free') }}</div>
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-list-ul me-1"></i>{{ __('All vehicles') }}</div>
            <div class="table-responsive"><table class="table table-erp table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Vehicle') }}</th><th>{{ __('Job card') }}</th><th>{{ __('Location') }}</th><th>{{ __('Key') }}</th><th></th></tr></thead>
                <tbody>@forelse($cards as $jc)
                    <tr><td><span class="font-monospace fw-semibold">{{ $jc->vehicle?->plate() }}</span><div class="small text-body-secondary">{{ $jc->vehicle?->title() }}</div></td>
                        <td><a href="{{ route('job-cards.show', $jc) }}">{{ $jc->number }}</a><div class="small">{{ status_label($jc->status) }}</div></td>
                        <td>{{ $jc->yardLocation?->displayName() ?? '—' }}</td>
                        <td>@if($jc->keyHolder)<span class="badge text-bg-warning"><i class="bi bi-person-badge"></i> {{ $jc->keyHolder->name }}</span>@elseif($jc->key_hook)<span class="badge text-bg-light"><i class="bi bi-key"></i> {{ $jc->key_hook }}</span>@else<span class="text-body-secondary">—</span>@endif
                            @if($jc->key_tag)<div class="small font-monospace text-body-secondary">{{ $jc->key_tag }}</div>@endif</td>
                        <td class="text-end"><a href="{{ route('tracking.card', $jc) }}" class="btn btn-sm btn-primary">{{ __('Open') }}</a></td></tr>
                @empty<tr><td colspan="5"><div class="empty-state py-3"><i class="bi bi-car-front"></i>{{ __('No vehicles in the workshop.') }}</div></td></tr>@endforelse</tbody>
            </table></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-badge me-1"></i>{{ __('Keys with employees') }}</div>
            <ul class="list-group list-group-flush">
                @forelse($keysOut as $jc)
                    <li class="list-group-item d-flex align-items-center gap-2"><a href="{{ route('tracking.card', $jc) }}" class="font-monospace fw-semibold">{{ $jc->vehicle?->plate() }}</a><span class="small">{{ $jc->keyHolder?->name }}</span></li>
                @empty<li class="list-group-item small text-body-secondary">{{ __('All keys are in the cabinet.') }}</li>@endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-signpost-2 me-1"></i>{{ __('Test drives') }}</div>
            <ul class="list-group list-group-flush">
                @forelse($drives as $td)
                    <li class="list-group-item small">
                        <div class="d-flex align-items-center gap-2"><a href="{{ route('tracking.card', $td->job_card_id) }}" class="font-monospace fw-semibold">{{ $td->vehicle?->plate() }}</a>
                            <span class="badge {{ $td->status === 'out' ? 'text-bg-danger' : 'text-bg-secondary' }}">{{ $td->status === 'out' ? __('On the road') : __('Approved') }}</span><span class="ms-auto">{{ $td->number }}</span></div>
                        <div class="text-body-secondary">{{ $td->driver?->name }}@if($td->out_at) · {{ __('since') }} {{ $td->out_at->format('H:i') }} · {{ number_format($td->odometer_out) }} km @endif</div>
                    </li>
                @empty<li class="list-group-item small text-body-secondary">{{ __('No open test drives.') }}</li>@endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
