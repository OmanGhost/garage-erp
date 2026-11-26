@extends('layouts.app')
@section('title', $resource->title())
@section('content')
@php
    $columns = $resource->columns();
    $sort = request('sort'); $dir = request('dir', 'desc');
    $sortUrl = fn($c) => request()->fullUrlWithQuery(['sort' => $c, 'dir' => $sort === $c && $dir === 'asc' ? 'desc' : 'asc', 'page' => null]);
@endphp
<x-page-header :title="$resource->title()" :icon="$resource::$icon" :breadcrumbs="[$resource->title()]">
    @foreach($resource->headerActions() as $a)
        <a href="{{ $a['url'] }}" class="btn {{ $a['class'] ?? 'btn-outline-secondary' }}"><i class="bi {{ $a['icon'] ?? 'bi-lightning' }}"></i> {{ $a['label'] }}</a>
    @endforeach
    @if($resource->can('create') && $resource->createUrl())
        <a href="{{ $resource->createUrl() }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add new') }}</a>
    @endif
</x-page-header>

<div class="card">
    @include('crud._filters')
    <div class="table-responsive">
        <table class="table table-hover table-erp">
            <thead>
            <tr>
                @foreach($columns as $c)
                    <th>
                        @if($c->sortable && !$c->relation && !$c->displayUsing)
                            <a href="{{ $sortUrl($c->name) }}">{{ $c->getLabel() }}
                                @if($sort === $c->name)<i class="bi bi-caret-{{ $dir === 'asc' ? 'up' : 'down' }}-fill"></i>@endif</a>
                        @else
                            {{ $c->getLabel() }}
                        @endif
                    </th>
                @endforeach
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php $show = $resource->showUrl($row); @endphp
                <tr>
                    @foreach($columns as $i => $c)
                        <td>
                            @if($i === 0 && $show)
                                <a href="{{ $show }}" class="fw-semibold text-decoration-none">{!! $c->render($row) !!}</a>
                            @else
                                {!! $c->render($row) !!}
                            @endif
                        </td>
                    @endforeach
                    <td class="table-actions">
                        @foreach($resource->rowActions($row) as $a)
                            @if(($a['method'] ?? 'get') === 'get')
                                <a href="{{ $a['url'] }}" class="btn btn-sm {{ $a['class'] ?? 'btn-light' }}" title="{{ $a['label'] }}" @if(!empty($a['blank'])) target="_blank" rel="noopener" @endif><i class="bi {{ $a['icon'] ?? 'bi-lightning' }}"></i></a>
                            @else
                                <form method="post" action="{{ $a['url'] }}" class="d-inline" @if(!empty($a['confirm'])) data-confirm="{{ $a['confirm'] }}" @endif>@csrf <x-idem/>
                                    <button class="btn btn-sm {{ $a['class'] ?? 'btn-light' }}" title="{{ $a['label'] }}"><i class="bi {{ $a['icon'] ?? 'bi-lightning' }}"></i></button>
                                </form>
                            @endif
                        @endforeach
                        @if($show)<a href="{{ $show }}" class="btn btn-sm btn-light" title="{{ __('View') }}"><i class="bi bi-eye"></i></a>@endif
                        @if($resource->can('update') && $resource->canEditRecord($row) && $resource->editUrl($row))
                            <a href="{{ $resource->editUrl($row) }}" class="btn btn-sm btn-light" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                        @endif
                        @if($resource->can('delete') && !$resource->deleteBlocker($row))
                            <form method="post" action="{{ route('crud.destroy', [$slug, $row->getKey()]) }}" class="d-inline" data-confirm="{{ __('Delete «:name»? This cannot be undone.', ['name' => $resource->recordLabel($row)]) }}">@csrf @method('delete')
                                <button class="btn btn-sm btn-light text-danger" title="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 1 }}"><div class="empty-state"><i class="bi bi-inbox"></i>{{ __('No records found.') }}</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('crud._pager')
</div>
@endsection
