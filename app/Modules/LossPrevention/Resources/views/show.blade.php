@extends('layouts.app')
@section('title', __('Alert').' #'.$a->id)
@section('content')
@php
    $sevColor = ['high' => 'danger', 'medium' => 'warning', 'low' => 'secondary'];
    $url = $a->subjectUrl();
    $own = (int) $a->user_id === (int) auth()->id();
@endphp
<x-page-header :title="$a->title" icon="bi-incognito" :breadcrumbs="[__('Fraud & loss prevention') => route('loss-prevention.index'), '#'.$a->id]">
    <a href="{{ route('loss-prevention.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> {{ __('Back') }}</a>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-4">{{ __('Rule') }}</dt><dd class="col-8">{{ status_label($a->rule) }}</dd>
                <dt class="col-4">{{ __('Severity') }}</dt><dd class="col-8"><span class="badge text-bg-{{ $sevColor[$a->severity] ?? 'secondary' }}">{{ status_label($a->severity) }}</span></dd>
                <dt class="col-4">{{ __('Done by') }}</dt><dd class="col-8">{{ $a->user?->name ?? __('System') }}</dd>
                <dt class="col-4">{{ __('First seen') }}</dt><dd class="col-8">{{ $a->created_at?->format('Y-m-d H:i') }}</dd>
                @if($a->occurrences > 1)<dt class="col-4">{{ __('Occurrences') }}</dt><dd class="col-8">{{ $a->occurrences }} · {{ __('last') }} {{ $a->last_seen_at?->format('Y-m-d H:i') }}</dd>@endif
                @if($a->amount !== null)<dt class="col-4">{{ __('Amount') }}</dt><dd class="col-8 font-monospace">{{ money($a->amount) }}</dd>@endif
                @if($url)<dt class="col-4">{{ __('Record') }}</dt><dd class="col-8"><a href="{{ $url }}">{{ __('Open the record') }} <i class="bi bi-box-arrow-up-left"></i></a></dd>@endif
                <dt class="col-4">{{ __('Status') }}</dt><dd class="col-8">{!! status_badge($a->status) !!}</dd>
            </dl>
        </div></div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">{{ __('Details') }}</div>
            <div class="card-body small">
                @foreach($a->details ?? [] as $k => $v)
                    <div class="mb-2"><span class="text-body-secondary">{{ __(\Illuminate\Support\Str::headline($k)) }}:</span>
                        @if(is_array($v))
                            <ul class="mb-0">@foreach($v as $row)<li>{{ is_array($row) ? collect($row)->map(fn ($x, $kk) => __(\Illuminate\Support\Str::headline($kk)).': '.(is_bool($x) ? ($x ? __('Yes') : __('No')) : $x))->implode(' · ') : $row }}</li>@endforeach</ul>
                        @else
                            <b>{{ is_bool($v) ? ($v ? __('Yes') : __('No')) : ($v ?? '—') }}</b>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        @if($a->status !== 'unreviewed')
            <div class="card mb-3 border-{{ $a->status === 'substantiated' ? 'danger' : 'success' }}"><div class="card-body small">
                <div class="fw-semibold mb-1">{!! status_badge($a->status) !!} {{ $a->reviewer?->name }} · {{ $a->reviewed_at?->format('Y-m-d H:i') }}</div>
                <div>{!! nl2br(e($a->review_notes)) !!}</div>
            </div></div>
        @endif

        @can('fraud_alerts.update')
            @if($own)
                <div class="alert alert-warning small"><i class="bi bi-person-lock"></i> {{ __('This alert is about your own action; another manager must review it.') }}</div>
            @else
                <form method="post" action="{{ route('loss-prevention.review', $a) }}" class="card">@csrf
                    <div class="card-header fw-semibold">{{ $a->status === 'unreviewed' ? __('Review') : __('Change review') }}</div>
                    <div class="card-body">
                        <div class="d-flex gap-3 mb-2">
                            <div class="form-check"><input class="form-check-input" type="radio" name="status" value="cleared" id="stC" required @checked($a->status === 'cleared')><label class="form-check-label" for="stC">{{ __('Checked — justified') }}</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="status" value="substantiated" id="stS" @checked($a->status === 'substantiated')><label class="form-check-label text-danger" for="stS">{{ __('Confirmed misconduct') }}</label></div>
                        </div>
                        <textarea name="review_notes" class="form-control" rows="3" required minlength="5" maxlength="2000" placeholder="{{ __('What did you find? (required)') }}" aria-label="{{ __('Review notes') }}"></textarea>
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Save review') }}</button></div>
                </form>
            @endif
        @endcan
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold">{{ __('Other alerts for :u', ['u' => $a->user?->name ?? __('System')]) }}</div>
            <ul class="list-group list-group-flush small">
                @forelse($history as $h)
                    <li class="list-group-item d-flex gap-2"><a href="{{ route('loss-prevention.show', $h) }}">{{ $h->title }}</a><span class="ms-auto text-nowrap">{{ $h->created_at?->format('m-d') }} {!! status_badge($h->status) !!}</span></li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('Nothing yet.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
