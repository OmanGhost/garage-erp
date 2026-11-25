@extends('layouts.portal')
@section('title', __('Complaints & feedback'))
@section('content')
<h5 class="mb-3">{{ __('Complaints & feedback') }}</h5>
<form method="post" action="{{ route('portal.complaints.store') }}" class="card mb-3">@csrf<div class="card-body">
    <input name="subject" class="form-control mb-2" placeholder="{{ __('Subject') }}" required>
    <select name="job_card_id" class="form-select mb-2"><option value="">{{ __('Related visit (optional)') }}</option>@foreach($jobs as $j)<option value="{{ $j->id }}">{{ $j->number }} · {{ $j->received_at->format('Y-m-d') }}</option>@endforeach</select>
    <textarea name="description" class="form-control mb-2" rows="3" placeholder="{{ __('Details') }}" required></textarea>
    <button class="btn btn-primary w-100">{{ __('Submit') }}</button>
</div></form>
@foreach($complaints as $c)<div class="card mb-2"><div class="card-body small"><div class="d-flex"><b>{{ $c->subject }}</b><span class="ms-auto">{!! status_badge($c->status) !!}</span></div><div class="text-body-secondary">{{ $c->created_at->format('Y-m-d') }}</div>@if($c->resolution)<div class="mt-1">{{ $c->resolution }}</div>@endif</div></div>@endforeach
@endsection
