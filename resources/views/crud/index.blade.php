@extends('layouts.app')
@section('title', $resource->title())
@section('content')
@php
    $columns = $resource->columns();
    $filters = $resource->filters();
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
    <div class="card-body border-bottom">
        <form method="get" class="row g-2 align-items-end filters-bar">
            <div class="col-12 col-md-4 col-xl-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Search...') }}" data-scan-target>
                </div>
            </div>
            @foreach($filters as $f)
                <div class="col-6 col-md-auto">
                    @if($f->type === 'checkbox')
                        <select name="f_{{ $f->name }}" class="form-select" onchange="this.form.submit()">
                            <option value="">{{ $f->getLabel() }}: {{ __('All') }}</option>
                            <option value="1" @selected(request('f_'.$f->name) === '1')>{{ __('Yes') }}</option>
                            <option value="0" @selected(request('f_'.$f->name) === '0')>{{ __('No') }}</option>
                        </select>
                    @else
                        <select name="f_{{ $f->name }}" class="form-select" onchange="this.form.submit()">
                            <option value="">{{ $f->getLabel() }}: {{ __('All') }}</option>
                            @foreach($f->getOptions() as $k => $v)
                                <option value="{{ $k }}" @selected((string) request('f_'.$f->name) === (string) $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            @endforeach
            @if($resource::$dateColumn)
                <div class="col-6 col-md-auto"><input type="date" name="from" value="{{ request('from') }}" class="form-control" title="{{ __('From') }}"></div>
                <div class="col-6 col-md-auto"><input type="date" name="to" value="{{ request('to') }}" class="form-control" title="{{ __('To') }}"></div>
            @endif
            <div class="col-auto"><button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button></div>
            @if(request()->hasAny(['q', 'from', 'to']) || collect(request()->query())->keys()->contains(fn($k) => str_starts_with($k, 'f_')))
                <div class="col-auto"><a href="{{ url()->current() }}" class="btn btn-link">{{ __('Reset') }}</a></div>
            @endif
            <div class="col-auto ms-md-auto d-flex gap-1">
                @if($resource->can('export'))
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download"></i> {{ __('Export') }}</button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}"><i class="bi bi-file-earmark-excel text-success me-2"></i>Excel</a></li>
                            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
                            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>PDF</a></li>
                        </ul>
                    </div>
                @endif
                @if($resource->can('print') || $resource->can('export'))
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'print']) }}" target="_blank" class="btn btn-outline-secondary" title="{{ __('Print') }}"><i class="bi bi-printer"></i></a>
                @endif
            </div>
        </form>
    </div>
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
                                <a href="{{ $a['url'] }}" class="btn btn-sm {{ $a['class'] ?? 'btn-light' }}" title="{{ $a['label'] }}"><i class="bi {{ $a['icon'] ?? 'bi-lightning' }}"></i></a>
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
                            <form method="post" action="{{ route('crud.destroy', [$slug, $row->getKey()]) }}" class="d-inline" data-confirm="{{ __('Delete this record?') }}">@csrf @method('delete')
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
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="small text-body-secondary">{{ __('Showing :a-:b of :t', ['a' => $rows->firstItem() ?? 0, 'b' => $rows->lastItem() ?? 0, 't' => $rows->total()]) }}
            <select class="form-select form-select-sm d-inline-block w-auto ms-2" onchange="location.href=this.value">
                @foreach([10, 25, 50, 100] as $pp)
                    <option value="{{ request()->fullUrlWithQuery(['per_page' => $pp, 'page' => null]) }}" @selected($rows->perPage() === $pp)>{{ $pp }}</option>
                @endforeach
            </select>
        </div>
        {{ $rows->links() }}
    </div>
</div>
@endsection
