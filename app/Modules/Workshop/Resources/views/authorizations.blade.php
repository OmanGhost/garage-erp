@extends('layouts.app')
@section('title', __('Handover Authorizations'))
@section('content')
@php $colors = ['pending' => 'warning', 'active' => 'success', 'used' => 'secondary', 'revoked' => 'danger', 'expired' => 'secondary', 'not_started' => 'info']; @endphp
<x-page-header :title="__('Handover Authorizations')" icon="bi-person-badge" :subtitle="__('People authorised by the vehicle owner to drop off or pick up the vehicle.')">
    @can('handover_authorizations.create')<a class="btn btn-primary" href="{{ route('handover-authorizations.create') }}"><i class="bi bi-plus-lg"></i> {{ __('New authorization') }}</a>@endcan
</x-page-header>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-sm {{ request('status') ? 'btn-outline-dark' : 'btn-dark' }}" href="{{ route('handover-authorizations.index') }}">{{ __('All') }} <span class="badge text-bg-light">{{ $counts->sum() }}</span></a>
    @foreach(['pending', 'active', 'used', 'revoked'] as $s)
        <a class="btn btn-sm {{ request('status') === $s ? 'btn-'.$colors[$s] : 'btn-outline-'.$colors[$s] }}" href="{{ route('handover-authorizations.index', ['status' => $s]) }}">{{ status_label($s) }} <span class="badge text-bg-light">{{ $counts[$s] ?? 0 }}</span></a>
    @endforeach
    <form class="ms-auto d-flex gap-2" method="get"><input type="hidden" name="status" value="{{ request('status') }}">
        <input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="{{ __('Number, person, ID, plate or owner...') }}" aria-label="{{ __('Search') }}">
        <button class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Search') }}"><i class="bi bi-search"></i></button></form>
</div>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead class="table-light small"><tr><th>{{ __('Number') }}</th><th>{{ __('Authorized person') }}</th><th>{{ __('Vehicle / owner') }}</th><th>{{ __('Type') }}</th><th>{{ __('Valid') }}</th><th>{{ __('Status') }}</th><th class="text-end"></th></tr></thead>
    <tbody>
    @forelse($list as $a)
        @php $st = $a->effectiveStatus(); @endphp
        <tr>
            <td><a class="fw-semibold font-monospace" href="{{ route('handover-authorizations.show', $a) }}">{{ $a->number }}</a></td>
            <td>{{ $a->person_name }}<div class="small text-body-secondary"><bdi dir="ltr">{{ $a->id_number }}</bdi>@if($a->relationship) · {{ status_label($a->relationship) }}@endif</div></td>
            <td class="small"><span dir="ltr" class="fw-semibold">{{ $a->vehicle?->plate() }}</span> {{ $a->vehicle?->title() }}<div class="text-body-secondary">{{ $a->customer?->name }}@if($a->jobCard) · {{ $a->jobCard->number }}@endif</div></td>
            <td class="small">{{ status_label($a->type) }}</td>
            <td class="small">{{ $a->valid_from?->format('Y-m-d') }} → {{ $a->valid_until?->format('Y-m-d') ?? __('until used') }}<div class="text-body-secondary">{{ $a->single_use ? __('Single use') : __('Multiple use') }}</div></td>
            <td><span class="badge text-bg-{{ $colors[$st] ?? 'secondary' }}">{{ status_label($st) }}</span></td>
            <td class="text-end text-nowrap">@can('handover_authorizations.print')<a class="btn btn-sm btn-outline-secondary" href="{{ route('handover-authorizations.print', $a) }}" target="_blank" rel="noopener" aria-label="{{ __('Print') }}" title="{{ __('Print') }}"><i class="bi bi-printer"></i></a>@endcan
                <a class="btn btn-sm btn-primary" href="{{ route('handover-authorizations.show', $a) }}">{{ __('Open') }}</a></td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center text-body-secondary py-5"><i class="bi bi-person-badge fs-1 d-block mb-2"></i>{{ __('No records found.') }}</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $list->links() }}</div>
@endsection
