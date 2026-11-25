@extends('layouts.app')
@section('title', __('Reports Center'))
@section('content')
<x-page-header :title="__('Reports Center')" icon="bi-bar-chart-line" :breadcrumbs="[__('Reports Center')]">
    <a href="{{ route('accounting.statements') }}" class="btn btn-outline-primary"><i class="bi bi-file-bar-graph"></i> {{ __('Financial statements') }}</a>
</x-page-header>
<div class="row g-3">
    @foreach($groups as $group => $items)
        <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-header">{{ $group }}</div><div class="list-group list-group-flush">
            @foreach($items as $key => $label)<a href="{{ route('reports.show', $key) }}" class="list-group-item list-group-item-action d-flex align-items-center"><i class="bi bi-file-earmark-bar-graph me-2 text-brand"></i>{{ $label }}<i class="bi bi-chevron-{{ is_rtl() ? 'left' : 'right' }} ms-auto small"></i></a>@endforeach
        </div></div></div>
    @endforeach
</div>
@endsection
