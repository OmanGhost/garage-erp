@extends('layouts.app')
@section('title', __('Workshop Board'))
@section('content')
<x-page-header :title="__('Workshop Board')" icon="bi-kanban" :breadcrumbs="[__('Workshop Board')]" :subtitle="__('Drag cards between columns. Allowed moves follow the workflow and your permissions.')">
    @can('reception.create')<a href="{{ route('reception.create') }}" class="btn btn-primary"><i class="bi bi-box-arrow-in-down"></i> {{ __('Receive vehicle') }}</a>@endcan
    <a href="{{ route('crud.index', ['job-cards', 'open' => 1]) }}" class="btn btn-outline-secondary"><i class="bi bi-list-ul"></i> {{ __('List view') }}</a>
</x-page-header>
<livewire:kanban-board />
@endsection
@push('scripts')
<script src="{{ asset('vendor/sortable/Sortable.min.js') }}"></script>
@endpush
