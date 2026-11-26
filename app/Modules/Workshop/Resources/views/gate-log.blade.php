@extends('layouts.app')
@section('title', __('Vehicle Gate Log'))
@section('content')
<x-page-header :title="__('Vehicle Gate Log')" icon="bi-signpost-split" :subtitle="__('Every vehicle entering or leaving the workshop: automatic at reception and delivery, or recorded at the gate.')">
    @can('reception.create')<button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#gate-new" aria-expanded="false"><i class="bi bi-plus-lg"></i> {{ __('Record movement') }}</button>@endcan
</x-page-header>

<div class="row g-2 mb-3">
    @foreach([
        [__('Entries today'), $stats['in_today'], 'bi-box-arrow-in-down', 'primary'],
        [__('Exits today'), $stats['out_today'], 'bi-box-arrow-up', 'success'],
        [__('Vehicles in the workshop'), $stats['inside'], 'bi-car-front', 'warning'],
        [__('Handovers by authorized persons'), $stats['authorized'], 'bi-person-badge', 'info'],
    ] as [$l, $v, $icon, $c])
        <div class="col-6 col-lg-3"><div class="card kpi h-100"><div class="card-body d-flex align-items-center gap-3">
            <span class="kpi-icon bg-{{ $c }}-subtle text-{{ $c }}"><i class="bi {{ $icon }}"></i></span>
            <div><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-4">{{ $v }}</div></div>
        </div></div></div>
    @endforeach
</div>

@can('reception.create')
<div class="collapse {{ $errors->any() ? 'show' : '' }}" id="gate-new">
    <form class="card card-body mb-3" method="post" action="{{ route('gate-logs.store') }}">@csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small mb-0">{{ __('Direction') }}</label>
                <select name="direction" class="form-select"><option value="in">{{ __('Entry') }}</option><option value="out" @selected(old('direction') === 'out')>{{ __('Exit') }}</option></select></div>
            <div class="col-md-3"><label class="form-label small mb-0 required">{{ __('Vehicle') }}</label><x-lookup name="vehicle_id" type="vehicles" :placeholder="__('Search plate / VIN...')"/>
                @error('vehicle_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            <div class="col-md-2"><label class="form-label small mb-0" for="g_odo">{{ __('Odometer') }}</label><input id="g_odo" type="number" name="odometer" class="form-control" min="0" value="{{ old('odometer') }}"></div>
            <div class="col-md-2"><label class="form-label small mb-0" for="g_person">{{ __('Driver / person') }}</label><input id="g_person" name="person_name" class="form-control" value="{{ old('person_name') }}"></div>
            <div class="col-md-2"><label class="form-label small mb-0" for="g_id">{{ __('ID number') }}</label><input id="g_id" name="person_id_number" class="form-control" dir="ltr" value="{{ old('person_id_number') }}"></div>
            <div class="col-md-1 d-grid"><button class="btn btn-success"><i class="bi bi-check2"></i></button></div>
            <div class="col-12"><input name="notes" class="form-control form-control-sm" placeholder="{{ __('Reason (road test, towing, returned for rework…)') }}" value="{{ old('notes') }}" aria-label="{{ __('Notes') }}"></div>
        </div>
    </form>
</div>
@endcan

<form class="card card-body py-2 mb-3" method="get">
    <div class="row g-2 align-items-center">
        <div class="col-md-4"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="{{ __('Plate, VIN, job card or person...') }}" aria-label="{{ __('Search') }}"></div>
        <div class="col-6 col-md-2"><input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}" aria-label="{{ __('From') }}"></div>
        <div class="col-6 col-md-2"><input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}" aria-label="{{ __('To') }}"></div>
        <div class="col-md-2"><select name="direction" class="form-select form-select-sm" aria-label="{{ __('Direction') }}"><option value="">{{ __('Entries & exits') }}</option><option value="in" @selected(request('direction') === 'in')>{{ __('Entries') }}</option><option value="out" @selected(request('direction') === 'out')>{{ __('Exits') }}</option></select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-sm btn-primary"><i class="bi bi-search"></i> {{ __('Search') }}</button></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light small"><tr><th>{{ __('Reference') }}</th><th>{{ __('Time') }}</th><th>{{ __('Movement') }}</th><th>{{ __('Vehicle') }}</th><th>{{ __('Customer / job card') }}</th><th>{{ __('Brought / collected by') }}</th><th>{{ __('Staff') }}</th><th class="text-end"></th></tr></thead>
        <tbody>
        @forelse($logs as $g)
            @php $jc = $g->jobCard; @endphp
            <tr>
                <td class="font-monospace small fw-semibold">{{ $g->reference() }}</td>
                <td class="small">{{ $g->logged_at?->format('Y-m-d') }}<div class="text-body-secondary">{{ $g->logged_at?->format('H:i') }}</div></td>
                <td>@if($g->direction === 'in')<span class="badge text-bg-primary"><i class="bi bi-box-arrow-in-down"></i> {{ __('Entry') }}</span>@else<span class="badge text-bg-success"><i class="bi bi-box-arrow-up"></i> {{ __('Exit') }}</span>@endif
                    @if($g->notes)<div class="small text-body-secondary mt-1">{{ \Illuminate\Support\Str::limit($g->notes, 40) }}</div>@endif</td>
                <td><span class="fw-semibold" dir="ltr">{{ $g->vehicle?->plate() }}</span><div class="small text-body-secondary">{{ $g->vehicle?->title() }}@if($g->odometer) · {{ number_format($g->odometer) }} km @endif</div></td>
                <td class="small">{{ $jc?->customer?->name ?? $g->vehicle?->customer?->name }}@if($jc)<div><a href="{{ route('job-cards.show', $jc) }}">{{ $jc->number }}</a></div>@endif</td>
                <td class="small">
                    @if($g->authorization)<span class="badge text-bg-info"><i class="bi bi-person-badge"></i> {{ __('Authorized') }}</span> {{ $g->person_name }}<div class="text-body-secondary"><a href="{{ route('handover-authorizations.show', $g->authorization) }}">{{ $g->authorization->number }}</a> · <bdi dir="ltr">{{ $g->person_id_number }}</bdi></div>
                    @elseif($g->person_name){{ $g->person_name }}@if($g->person_id_number)<div class="text-body-secondary"><bdi dir="ltr">{{ $g->person_id_number }}</bdi></div>@endif
                    @else<span class="text-body-tertiary">{{ __('Owner') }}</span>@endif
                </td>
                <td class="small">{{ $g->user?->name ?? '—' }}</td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('gate-logs.print', $g) }}" target="_blank" rel="noopener" title="{{ __('Print') }}" aria-label="{{ __('Print') }}"><i class="bi bi-printer"></i></a>
                    @if($g->direction === 'out' && $jc)@can('job_cards.print')<a class="btn btn-sm btn-outline-success" href="{{ route('job-cards.gate-pass', $jc) }}" target="_blank" rel="noopener" title="{{ __('Gate pass') }}" aria-label="{{ __('Gate pass') }}"><i class="bi bi-door-open"></i></a>@endcan @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-body-secondary py-5"><i class="bi bi-signpost-split fs-1 d-block mb-2"></i>{{ __('No movements in this period.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
