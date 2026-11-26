@extends('layouts.app')
@section('title', $model ? __('Edit').' · '.$resource->singular() : __('Add new').' · '.$resource->singular())
@section('content')
<x-page-header :title="$model ? __('Edit').' — '.$resource->singular() : __('Add new').' — '.$resource->singular()" :icon="$resource::$icon"
               :breadcrumbs="[$resource->title() => $resource->indexUrl(), $model ? __('Edit') : __('Add new')]">
    <a href="{{ $model ? ($resource->showUrl($model) ?? $resource->indexUrl()) : $resource->indexUrl() }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-{{ is_rtl() ? 'right' : 'left' }}"></i> {{ __('Back') }}</a>
</x-page-header>

<form method="post" enctype="multipart/form-data" action="{{ $model ? route('crud.update', [$slug, $model->getKey()]) : route('crud.store', $slug) }}">
    @csrf
    @if($model) @method('put') @endif
    <x-idem/>
    <div class="card">
        <div class="card-body">
            <div class="row">
                @php $section = null; @endphp
                @foreach($resource->formFields($model) as $f)
                    @if($f->section && $f->section !== $section)
                        @php $section = $f->section; @endphp
                        <div class="col-12"><div class="form-section-title">{{ $section }}</div></div>
                    @endif
                    @include('crud._field', ['f' => $f, 'model' => $model])
                @endforeach
                @if($extras = $resource->formExtras($model))
                    @include($extras, ['model' => $model])
                @endif
                @if($resource::$hasAttachments)
                    <div class="col-12 mb-3">
                        <label class="form-label">{{ __('Attachments / documents') }}</label>
                        <input type="file" name="attachments[]" multiple class="form-control">
                        <div class="form-text">{{ __('Max :n MB per file.', ['n' => round(config('erp.uploads.max_kb') / 1024)]) }}</div>
                    </div>
                @endif
            </div>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            @unless($model)
                <button class="btn btn-outline-primary" name="_continue" value="1"><i class="bi bi-plus-square"></i> {{ __('Save & add another') }}</button>
            @endunless
            <button class="btn btn-primary"><i class="bi bi-check2"></i> {{ __('Save') }}</button>
        </div>
    </div>
</form>
@endsection
