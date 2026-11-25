@extends('layouts.app')
@section('title', $resource->singular())
@section('content')
<x-page-header :title="$resource->singular().' #'.$model->getKey()" :icon="$resource::$icon" :breadcrumbs="[$resource->title() => $resource->indexUrl(), '#'.$model->getKey()]">
    @foreach($resource->rowActions($model) as $a)
        @if(($a['method'] ?? 'get') === 'get')
            <a href="{{ $a['url'] }}" class="btn {{ $a['class'] ?? 'btn-outline-secondary' }}" @if(!empty($a['blank'])) target="_blank" rel="noopener" @endif><i class="bi {{ $a['icon'] ?? 'bi-lightning' }}"></i> {{ $a['label'] }}</a>
        @else
            <form method="post" action="{{ $a['url'] }}" @if(!empty($a['confirm'])) data-confirm="{{ $a['confirm'] }}" @endif>@csrf <x-idem/>
                <button class="btn {{ $a['class'] ?? 'btn-outline-secondary' }}"><i class="bi {{ $a['icon'] ?? 'bi-lightning' }}"></i> {{ $a['label'] }}</button>
            </form>
        @endif
    @endforeach
    @if($resource->can('update') && $resource->canEditRecord($model) && $resource->editUrl($model))
        <a href="{{ $resource->editUrl($model) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
    @endif
</x-page-header>

<div class="row g-3">
    <div class="{{ $audits->isNotEmpty() ? 'col-xl-8' : 'col-12' }}">
        <div class="card mb-3">
            <div class="card-body">
                <dl class="row mb-0">
                    @foreach(array_filter($resource->fields(), fn($f) => $f->onShow) as $f)
                        <dt class="col-sm-4 col-lg-3 text-body-secondary fw-normal small">{{ $f->getLabel() }}</dt>
                        <dd class="col-sm-8 col-lg-9">{!! $f->render($model) !!}</dd>
                    @endforeach
                </dl>
            </div>
        </div>
        @foreach($resource->showPanels($model) as $panel)
            @include($panel['view'], $panel['data'] ?? [])
        @endforeach
        @if($resource::$hasAttachments)
            <x-attachments :model="$model" :can-upload="$resource->can('update')"/>
        @endif
    </div>
    @if($audits->isNotEmpty())
        <div class="col-xl-4">
            @include('partials.audit-trail', ['audits' => $audits])
        </div>
    @endif
</div>
@endsection
