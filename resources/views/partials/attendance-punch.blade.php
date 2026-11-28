{{-- One-click attendance in the top bar, for users linked to an active employee --}}
@php
    $punchEmployee = setting('hr.self_attendance', true) ? \App\Modules\HR\Services\AttendanceService::employeeFor(auth()->user()) : null;
    $punch = $punchEmployee ? app(\App\Modules\HR\Services\AttendanceService::class)->state($punchEmployee) : null;
@endphp
@if($punch)
    @php $a = $punch['attendance']; @endphp
    @if($punch['state'] === 'in')
        <form method="post" action="{{ route('attendance.punch') }}" class="d-inline">@csrf
            <button class="btn btn-sm btn-success punch-btn" title="{{ __('Clock in now') }}"><i class="bi bi-box-arrow-in-right"></i><span class="d-none d-lg-inline"> {{ __('Clock in') }}</span></button>
        </form>
    @elseif($punch['state'] === 'out')
        <form method="post" action="{{ route('attendance.punch') }}" class="d-inline" data-confirm="{{ __('Check out and end your work day?') }}">@csrf
            <button class="btn btn-sm btn-outline-danger punch-btn" title="{{ __('Checked in at :t', ['t' => $a->check_in->format('H:i')]) }}">
                <i class="bi bi-box-arrow-right"></i><span class="d-none d-lg-inline"> {{ __('Clock out') }}</span>
                <span class="badge text-bg-success ms-1">{{ $a->check_in->format('H:i') }}</span></button>
        </form>
    @else
        <span class="btn btn-sm btn-outline-secondary disabled punch-btn" title="{{ __('Work day completed') }}">
            <i class="bi bi-check2-circle"></i><span class="d-none d-lg-inline"> {{ $a?->check_in?->format('H:i') }} – {{ $a?->check_out?->format('H:i') }}</span></span>
    @endif
@endif
