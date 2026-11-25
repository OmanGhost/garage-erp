@extends('layouts.app')
@section('title', __('Daily attendance'))
@section('content')
<x-page-header :title="__('Daily attendance sheet')" icon="bi-calendar-check" :breadcrumbs="[__('Attendance') => route('crud.index', 'attendances'), __('Daily')]">
    <form class="d-flex gap-2"><input type="date" name="date" value="{{ $date }}" class="form-control" onchange="this.form.submit()"></form>
</x-page-header>
<form method="post" class="card">@csrf <x-idem/><input type="hidden" name="date" value="{{ $date }}">
    <div class="table-responsive"><table class="table table-sm table-erp mb-0 align-middle">
        <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Status') }}</th><th>{{ __('Check in') }}</th><th>{{ __('Check out') }}</th><th>{{ __('Worked') }}</th></tr></thead>
        <tbody>@foreach($employees as $e)@php $a = $existing[$e->id] ?? null; @endphp
            <tr><td>{{ $e->code }} — {{ $e->name }}</td>
                <td><select name="rows[{{ $e->id }}][status]" class="form-select form-select-sm">@foreach(['present', 'late', 'absent', 'leave', 'holiday', 'weekend'] as $s)<option value="{{ $s }}" @selected(($a?->status ?? 'present') === $s)>{{ status_label($s) }}</option>@endforeach</select></td>
                <td><input type="time" name="rows[{{ $e->id }}][in]" value="{{ $a?->check_in?->format('H:i') ?? '08:00' }}" class="form-control form-control-sm"></td>
                <td><input type="time" name="rows[{{ $e->id }}][out]" value="{{ $a?->check_out?->format('H:i') }}" class="form-control form-control-sm"></td>
                <td class="small">{{ $a ? round($a->worked_minutes / 60, 1).' h' : '' }}</td></tr>
        @endforeach</tbody>
    </table></div>
    <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Save attendance') }}</button></div>
</form>
@endsection
