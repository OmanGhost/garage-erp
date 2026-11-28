@extends('layouts.admin')
@section('title', __('Website messages'))
@section('content')
@php
    $colors = ['new' => 'primary', 'replied' => 'success', 'archived' => 'secondary'];
    $topics = __('site_pages.contact.topics');
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-envelope text-brand"></i> {{ __('Website messages') }}</h4>
        <div class="small text-body-secondary">{{ __('Messages sent from the contact page of the website.') }}</div>
    </div>
    <div class="btn-group btn-group-sm">
        <a href="{{ route('admin.messages.index') }}" class="btn btn-outline-secondary {{ $status ? '' : 'active' }}">{{ __('All') }}</a>
        @foreach(\App\Modules\Saas\Models\ContactMessage::STATUSES as $st)
            <a href="{{ route('admin.messages.index', ['status' => $st]) }}" class="btn btn-outline-{{ $colors[$st] }} {{ $status === $st ? 'active' : '' }}">{{ status_label($st) }} <span class="badge text-bg-light">{{ $counts[$st] ?? 0 }}</span></a>
        @endforeach
    </div>
</div>
<div class="card">
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Name') }}</th><th>{{ __('Topic') }}</th><th>{{ __('Message') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
        @forelse($messages as $m)
            <tr class="{{ $m->status === 'new' ? 'fw-semibold' : '' }}" onclick="location.href='{{ route('admin.messages.show', $m) }}'" style="cursor:pointer">
                <td class="small text-nowrap">{{ $m->created_at->format('Y-m-d H:i') }}</td>
                <td><a href="{{ route('admin.messages.show', $m) }}">{{ $m->name }}</a><div class="small text-body-secondary">{{ $m->company }}</div></td>
                <td class="small">{{ $topics[$m->topic] ?? $m->topic }}</td>
                <td class="small text-body-secondary">{{ \Illuminate\Support\Str::limit($m->message, 90) }}</td>
                <td><span class="badge text-bg-{{ $colors[$m->status] ?? 'secondary' }}">{{ status_label($m->status) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-body-secondary p-4">{{ __('No messages yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($messages->hasPages())<div class="card-footer">{{ $messages->links() }}</div>@endif
</div>
@endsection
