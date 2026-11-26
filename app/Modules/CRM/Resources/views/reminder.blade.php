@extends('layouts.app')
@section('title', $r->title)
@section('content')
@php
    $phone = preg_replace('/\D/', '', (string) $r->customer?->phone);
    $wa = __('Hello :n, a reminder from :c: :s is due for your vehicle :p. Reply to book a visit.', ['n' => $r->customer?->name, 'c' => tenant()->company()?->displayName(), 's' => $r->title, 'p' => $r->vehicle?->plate()]);
    $msgStatus = ['queued' => ['warning', __('Waiting to be sent')], 'sent' => ['success', __('Delivered to the gateway')], 'failed' => ['danger', __('Failed')]];
    $outcomeIcon = ['message_sent' => 'bi-send text-info', 'reached' => 'bi-telephone-inbound text-success', 'acknowledged' => 'bi-person-check text-success',
        'booked' => 'bi-calendar-check text-success', 'no_answer' => 'bi-telephone-x text-warning', 'declined' => 'bi-hand-thumbs-down text-danger',
        'wrong_number' => 'bi-exclamation-triangle text-danger', 'failed' => 'bi-x-circle text-danger'];
@endphp
<x-page-header :title="$r->title" icon="bi-bell" :breadcrumbs="[__('Service Reminders') => route('reminders.index'), $r->customer?->name]"
    :subtitle="e($r->customer?->name).' · '.e($r->vehicle?->label()).' · '.status_badge($r->stage())">
    @if($phone)
        <a class="btn btn-outline-secondary" href="tel:+{{ $phone }}"><i class="bi bi-telephone"></i> {{ __('Call') }}</a>
        <a class="btn btn-outline-success" href="https://wa.me/{{ $phone }}?text={{ rawurlencode($wa) }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
    @endif
    @can('service_reminders.update')
        <form method="post" action="{{ route('reminders.send', $r) }}">@csrf<button class="btn btn-outline-primary"><i class="bi bi-send"></i> {{ __('Send reminder message') }}</button></form>
        @unless(in_array($r->status, ['done', 'cancelled'], true))
            <form method="post" action="{{ route('reminders.complete', $r) }}" data-confirm="{{ __('Close this reminder (service done)?') }}" data-confirm-type="primary">@csrf<button class="btn btn-outline-secondary"><i class="bi bi-check2-circle"></i> {{ __('Close') }}</button></form>
        @endunless
    @endcan
</x-page-header>

<div class="row g-3 mb-3">
    @foreach([
        [__('Due date'), $r->due_date?->format('Y-m-d').' ('.$r->due_date?->diffForHumans().')', 'bi-calendar-event', $r->due_date?->isPast() ? 'danger' : 'primary'],
        [__('Due odometer'), $r->due_odometer ? number_format($r->due_odometer).' km' : '—', 'bi-speedometer2', 'secondary'],
        [__('Customer confirmed'), $r->acknowledged_at?->format('Y-m-d H:i') ?? __('Not yet'), 'bi-person-check', $r->acknowledged_at ? 'success' : 'warning'],
        [__('Appointment'), $r->appointment ? $r->appointment->number.' · '.$r->appointment->starts_at?->format('Y-m-d H:i') : __('Not booked'), 'bi-calendar-check', $r->appointment ? 'success' : 'secondary'],
    ] as [$l, $v, $icon, $c])
        <div class="col-6 col-lg-3"><div class="card kpi h-100"><div class="card-body d-flex align-items-center gap-3">
            <span class="kpi-icon bg-{{ $c }}-subtle text-{{ $c }}"><i class="bi {{ $icon }}"></i></span>
            <div><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-6">{{ $v }}</div></div>
        </div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clock-history"></i> {{ __('Contact history') }}</div>
            <ul class="list-group list-group-flush">
                @forelse($r->contacts as $ct)
                    <li class="list-group-item d-flex gap-3">
                        <i class="bi {{ $outcomeIcon[$ct->outcome] ?? 'bi-dot' }} fs-5"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ status_label($ct->outcome) }} <span class="badge text-bg-light border">{{ status_label($ct->channel) }}</span></div>
                            @if($ct->notificationLog)
                                @php [$mc, $ml] = $msgStatus[$ct->notificationLog->status] ?? ['secondary', $ct->notificationLog->status]; @endphp
                                <div class="small"><span class="badge text-bg-{{ $mc }}">{{ $ml }}</span> <bdi dir="ltr">{{ $ct->notificationLog->recipient }}</bdi>
                                    @if($ct->notificationLog->error)<span class="text-danger"> — {{ \Illuminate\Support\Str::limit($ct->notificationLog->error, 120) }}</span>@endif</div>
                                <div class="small text-body-secondary fst-italic">{{ \Illuminate\Support\Str::limit($ct->notificationLog->body, 160) }}</div>
                            @endif
                            @if($ct->notes)<div class="small" style="white-space:pre-line">{{ $ct->notes }}</div>@endif
                            <div class="small text-body-secondary">{{ $ct->contacted_at?->format('Y-m-d H:i') }} · {{ $ct->user?->name ?? __('System') }}</div>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('No contact yet. Send the reminder or call the customer and record the result.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-xl-5">
        @can('service_reminders.update')
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-telephone-plus"></i> {{ __('Record a contact') }}</div>
            <div class="card-body">
                <form method="post" action="{{ route('reminders.contact', $r) }}" class="row g-2">@csrf
                    <div class="col-6"><label class="form-label small mb-0" for="channel">{{ __('Channel') }}</label>
                        <select id="channel" name="channel" class="form-select">@foreach(\App\Modules\CRM\Models\ReminderContact::CHANNELS as $ch)@continue($ch === 'system')<option value="{{ $ch }}">{{ status_label($ch) }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label small mb-0" for="outcome">{{ __('Result') }}</label>
                        <select id="outcome" name="outcome" class="form-select">@foreach(['reached', 'acknowledged', 'no_answer', 'declined', 'wrong_number'] as $o)<option value="{{ $o }}">{{ status_label($o) }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label small mb-0" for="notes">{{ __('Notes') }}</label><textarea id="notes" name="notes" rows="2" class="form-control" placeholder="{{ __('e.g. will come next week') }}"></textarea></div>
                    <div class="col-7"><label class="form-label small mb-0" for="follow_up_on">{{ __('Follow up on') }}</label><input type="date" id="follow_up_on" name="follow_up_on" class="form-control" min="{{ today()->toDateString() }}"></div>
                    <div class="col-5 d-grid align-items-end"><button class="btn btn-primary mt-auto"><i class="bi bi-check2"></i> {{ __('Save') }}</button></div>
                </form>
            </div>
        </div>
        @endcan
        @can('appointments.create')
        @unless($r->appointment)
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-calendar-plus"></i> {{ __('Book the visit') }}</div>
            <div class="card-body">
                <form method="post" action="{{ route('reminders.book', $r) }}" class="row g-2">@csrf
                    <div class="col-7"><label class="form-label small mb-0" for="starts_at">{{ __('Date & time') }}</label><input type="datetime-local" id="starts_at" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror" required value="{{ old('starts_at') }}"></div>
                    <div class="col-5"><label class="form-label small mb-0" for="duration">{{ __('Duration (min)') }}</label><input type="number" id="duration" name="duration" class="form-control" value="60" min="15" max="480" step="15"></div>
                    <div class="col-12 d-grid"><button class="btn btn-success"><i class="bi bi-calendar-check"></i> {{ __('Book appointment') }}</button></div>
                </form>
            </div>
        </div>
        @endunless
        @endcan
        <div class="card">
            <div class="card-header"><i class="bi bi-person"></i> {{ __('Customer & vehicle') }}</div>
            <div class="card-body small">
                <a class="fw-semibold" href="{{ route('customers.show', $r->customer) }}">{{ $r->customer?->name }}</a> · <bdi dir="ltr">{{ $r->customer?->phone }}</bdi>
                <div class="text-body-secondary">{{ __('Preferred channel') }}: {{ status_label((string) ($r->customer?->preferred_channel ?: 'sms')) }} · {{ __('Marketing messages') }}: {{ $r->customer?->marketing_opt_in ? __('Yes') : __('No') }}</div>
                @if($r->vehicle)<div class="mt-2"><a href="{{ route('vehicles.show', $r->vehicle) }}">{{ $r->vehicle->label() }}</a> · {{ number_format((int) $r->vehicle->odometer) }} km</div>@endif
                @if($r->notes)<div class="mt-2">{{ $r->notes }}</div>@endif
            </div>
        </div>
    </div>
</div>
@endsection
