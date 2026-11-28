@extends('layouts.admin')
@section('title', __('Website messages').' · '.$m->name)
@section('content')
@php
    $topics = __('site_pages.contact.topics');
    $wa = preg_replace('/\D/', '', (string) $m->phone);
@endphp
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('admin.messages.index') }}">{{ __('Website messages') }}</a></li>
    <li class="breadcrumb-item active">{{ $m->name }}</li>
</ol></nav>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2"><b>{{ $topics[$m->topic] ?? $m->topic }}</b><span class="ms-auto small text-body-secondary">{{ $m->created_at->format('Y-m-d H:i') }}</span></div>
            <div class="card-body"><div style="white-space:pre-wrap" dir="auto">{{ $m->message }}</div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body small">
            <div class="fs-6 fw-bold">{{ $m->name }}</div>
            @if($m->company)<div class="text-body-secondary mb-2">{{ $m->company }}</div>@endif
            <div><i class="bi bi-envelope"></i> <a href="mailto:{{ $m->email }}?subject={{ rawurlencode($topics[$m->topic] ?? '') }}" dir="ltr">{{ $m->email }}</a></div>
            @if($m->phone)<div><i class="bi bi-telephone"></i> <a href="tel:{{ preg_replace('/[^\d+]/', '', $m->phone) }}" dir="ltr">{{ $m->phone }}</a>
                @if($wa)· <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i></a>@endif</div>@endif
            <div class="text-body-secondary mt-2">{{ strtoupper($m->locale) }} · {{ $m->ip_address }}</div>
        </div></div>
        <form method="post" action="{{ route('admin.messages.update', $m) }}" class="card">@csrf @method('PUT')
            <div class="card-body">
                <label class="form-label" for="st">{{ __('Status') }}</label>
                <select id="st" name="status" class="form-select mb-2">@foreach(\App\Modules\Saas\Models\ContactMessage::STATUSES as $st)<option value="{{ $st }}" @selected($m->status === $st)>{{ status_label($st) }}</option>@endforeach</select>
                <label class="form-label" for="nt">{{ __('Notes') }}</label>
                <textarea id="nt" name="admin_notes" class="form-control" rows="4">{{ $m->admin_notes }}</textarea>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Save') }}</button></div>
        </form>
    </div>
</div>
@endsection
