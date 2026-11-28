@extends('layouts.app')
@section('title', __('Role change requests'))
@section('content')
<x-page-header :title="__('Role change requests')" icon="bi-person-up" :breadcrumbs="[__('Administration'), __('Role change requests')]">
    @can('role_requests.create')<a href="{{ route('role-requests.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New request') }}</a>@endcan
</x-page-header>

<div class="card">
    <div class="card-header">
        <div class="btn-group btn-group-sm" role="group">
            <a href="{{ route('role-requests.index') }}" class="btn btn-outline-secondary {{ $status ? '' : 'active' }}">{{ __('All') }}</a>
            @foreach(['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'] as $st => $c)
                <a href="{{ route('role-requests.index', ['status' => $st]) }}" class="btn btn-outline-{{ $c }} {{ $status === $st ? 'active' : '' }}">{{ status_label($st) }} <span class="badge text-bg-light">{{ $counts[$st] ?? 0 }}</span></a>
            @endforeach
        </div>
    </div>
    <div class="table-responsive"><table class="table table-erp align-middle mb-0">
        <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('User') }}</th><th>{{ __('Current role') }}</th><th></th><th>{{ __('Requested role') }}</th><th>{{ __('Requested by') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($requests as $r)
            <tr>
                <td class="fw-semibold"><a href="{{ route('role-requests.show', $r) }}">{{ $r->number }}</a></td>
                <td class="small text-nowrap">{{ $r->created_at->format('Y-m-d') }}</td>
                <td>{{ $r->user?->name }}</td>
                <td>{{ $r->current_role ? __($r->current_role) : '—' }}</td>
                <td class="text-body-secondary"><i class="bi {{ is_rtl() ? 'bi-arrow-left' : 'bi-arrow-right' }}"></i></td>
                <td class="fw-semibold">{{ __($r->requested_role) }}</td>
                <td class="small">{{ $r->requester?->name }}</td>
                <td>{!! status_badge($r->status) !!}@if($r->decider)<div class="small text-body-secondary">{{ $r->decider->name }}</div>@endif</td>
                <td class="text-end"><a href="{{ route('role-requests.print', $r) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="{{ __('Print') }}" aria-label="{{ __('Print') }}"><i class="bi bi-printer"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="9"><div class="empty-state"><i class="bi bi-person-up"></i>{{ __('No role change requests.') }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($requests->hasPages())<div class="card-footer">{{ $requests->links() }}</div>@endif
</div>
@endsection
