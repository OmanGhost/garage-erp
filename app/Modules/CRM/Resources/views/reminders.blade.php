@extends('layouts.app')
@section('title', __('Periodic Maintenance Reminders'))
@section('content')
@php
    $meta = [
        'overdue' => ['danger', 'bi-exclamation-octagon', __('Overdue')], 'due' => ['primary', 'bi-calendar-event', __('Due')],
        'sent' => ['info', 'bi-send', __('Sent')], 'delivered' => ['info', 'bi-check2-all', __('Delivered')],
        'failed' => ['danger', 'bi-x-circle', __('Message failed')], 'acknowledged' => ['success', 'bi-person-check', __('Customer confirmed')],
        'booked' => ['success', 'bi-calendar-check', __('Booked')], 'done' => ['secondary', 'bi-check-circle', __('Done')], 'cancelled' => ['secondary', 'bi-slash-circle', __('Cancelled')],
    ];
@endphp
<x-page-header :title="__('Periodic Maintenance Reminders')" icon="bi-bell" :subtitle="__('Who is due, whether the reminder reached the customer, and the follow-up of each contact.')">
    @can('service_reminders.create')<a class="btn btn-primary" href="{{ route('crud.create', 'service-reminders') }}"><i class="bi bi-plus-lg"></i> {{ __('New reminder') }}</a>@endcan
</x-page-header>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3 col-xl"><a class="card kpi h-100 text-decoration-none {{ ! $stage ? 'border-dark' : '' }}" href="{{ request()->fullUrlWithQuery(['stage' => null]) }}"><div class="card-body py-2">
        <div class="kpi-label"><i class="bi bi-collection"></i> {{ __('All') }}</div><div class="kpi-value fs-4">{{ $total }}</div><div class="small text-body-secondary">{{ __(':n due this week', ['n' => $dueThisWeek]) }}</div></div></a></div>
    @foreach(['overdue', 'due', 'sent', 'delivered', 'failed', 'acknowledged', 'booked'] as $s)
        @php [$c, $i, $l] = $meta[$s]; @endphp
        <div class="col-6 col-md-3 col-xl"><a class="card kpi h-100 text-decoration-none {{ $stage === $s ? 'border-'.$c.' border-2' : '' }}" href="{{ request()->fullUrlWithQuery(['stage' => $s]) }}"><div class="card-body py-2">
            <div class="kpi-label text-{{ $c }}"><i class="bi {{ $i }}"></i> {{ $l }}</div><div class="kpi-value fs-4">{{ $counts[$s] ?? 0 }}</div></div></a></div>
    @endforeach
</div>

<form class="card card-body py-2 mb-3" method="get">
    <div class="row g-2 align-items-center">
        <input type="hidden" name="stage" value="{{ $stage }}">
        <div class="col-md-4"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="{{ __('Customer, mobile, plate or service...') }}"></div>
        <div class="col-6 col-md-2"><input type="date" class="form-control form-control-sm" name="from" value="{{ request('from') }}" aria-label="{{ __('From') }}"></div>
        <div class="col-6 col-md-2"><input type="date" class="form-control form-control-sm" name="to" value="{{ request('to') }}" aria-label="{{ __('To') }}"></div>
        <div class="col-md-2"><select name="view" class="form-select form-select-sm">@foreach(['active' => __('Open reminders'), 'done' => __('Closed'), 'all' => __('All')] as $k => $l)<option value="{{ $k }}" @selected($view === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-sm btn-primary"><i class="bi bi-search"></i> {{ __('Search') }}</button></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light small"><tr><th>{{ __('Customer') }}</th><th>{{ __('Vehicle') }}</th><th>{{ __('Service') }}</th><th>{{ __('Due') }}</th><th>{{ __('Reminder status') }}</th><th>{{ __('Last contact') }}</th><th class="text-end">{{ __('Contact') }}</th></tr></thead>
        <tbody>
        @forelse($list as $r)
            @php
                [$c, $i, $l] = $meta[$r->stage()];
                $phone = preg_replace('/\D/', '', (string) $r->customer?->phone);
                $last = $r->contacts->first();
                $wa = __('Hello :n, a reminder from :c: :s is due for your vehicle :p. Reply to book a visit.', ['n' => $r->customer?->name, 'c' => tenant()->company()?->displayName(), 's' => $r->title, 'p' => $r->vehicle?->plate()]);
            @endphp
            <tr>
                <td><a class="fw-semibold text-decoration-none" href="{{ route('reminders.show', $r) }}">{{ $r->customer?->name }}</a>
                    <div class="small text-body-secondary"><bdi dir="ltr">{{ $r->customer?->phone }}</bdi></div></td>
                <td class="small"><span class="fw-semibold" dir="ltr">{{ $r->vehicle?->plate() }}</span><div class="text-body-secondary">{{ $r->vehicle?->title() }}</div></td>
                <td class="small">{{ $r->title }}@if($r->due_odometer)<div class="text-body-secondary">{{ number_format($r->due_odometer) }} km</div>@endif</td>
                <td class="small {{ $r->due_date?->isPast() && ! in_array($r->status, ['done', 'cancelled'], true) ? 'text-danger fw-semibold' : '' }}">{{ $r->due_date?->format('Y-m-d') }}<div class="text-body-secondary">{{ $r->due_date?->diffForHumans() }}</div></td>
                <td><span class="badge text-bg-{{ $c }}"><i class="bi {{ $i }}"></i> {{ $l }}</span>
                    @if($r->contact_attempts)<div class="small text-body-secondary mt-1">{{ trans_choice('{1} :n attempt|[2,*] :n attempts', $r->contact_attempts, ['n' => $r->contact_attempts]) }}</div>@endif</td>
                <td class="small">@if($last){{ status_label($last->outcome) }} · {{ status_label($last->channel) }}<div class="text-body-secondary">{{ $last->contacted_at?->diffForHumans() }}</div>@else <span class="text-body-tertiary">—</span>@endif
                    @if($r->follow_up_on)<div class="text-warning"><i class="bi bi-alarm"></i> {{ $r->follow_up_on->format('Y-m-d') }}</div>@endif</td>
                <td class="text-end text-nowrap">
                    @if($phone)<a class="btn btn-sm btn-outline-secondary" href="tel:+{{ $phone }}" title="{{ __('Call') }}" aria-label="{{ __('Call') }}"><i class="bi bi-telephone"></i></a>
                        <a class="btn btn-sm btn-outline-success" href="https://wa.me/{{ $phone }}?text={{ rawurlencode($wa) }}" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>@endif
                    @can('service_reminders.update')
                        <form class="d-inline" method="post" action="{{ route('reminders.send', $r) }}">@csrf<button class="btn btn-sm btn-outline-primary" title="{{ __('Send reminder message') }}" aria-label="{{ __('Send reminder message') }}"><i class="bi bi-send"></i></button></form>
                    @endcan
                    <a class="btn btn-sm btn-primary" href="{{ route('reminders.show', $r) }}"><i class="bi bi-journal-text"></i> {{ __('Follow-up') }}</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-body-secondary py-5"><i class="bi bi-bell-slash fs-1 d-block mb-2"></i>{{ __('No records found.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
