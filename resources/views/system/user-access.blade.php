@extends('layouts.app')
@section('title', __('Access'))
@section('content')
<x-page-header :title="__('Branch, warehouse & department access').' — '.$user->name" icon="bi-diagram-2" :breadcrumbs="[__('Users') => route('crud.index', 'users'), $user->name]"
    :subtitle="__('Users with the \'All branches\' permission see every branch. Others see their default branch plus the branches selected here.')"/>
<form method="post" class="row g-3">@csrf @method('put')
    @foreach([['branches', $branches, $user->branches->pluck('id'), __('Branches'), fn($b) => $b->displayName()], ['warehouses', $warehouses, $user->warehouses->pluck('id'), __('Warehouses'), fn($w) => $w->displayName()], ['departments', $departments, $user->departments->pluck('id'), __('Workshop departments (technician)'), fn($d) => $d->displayName()]] as [$name, $items, $selected, $title, $label])
        <div class="col-md-4"><div class="card h-100"><div class="card-header">{{ $title }}</div><div class="card-body">
            @foreach($items as $i)<div class="form-check"><input class="form-check-input" type="checkbox" name="{{ $name }}[]" value="{{ $i->id }}" id="{{ $name }}{{ $i->id }}" @checked($selected->contains($i->id))><label class="form-check-label" for="{{ $name }}{{ $i->id }}">{{ $label($i) }}</label></div>@endforeach
        </div></div></div>
    @endforeach
    <div class="col-12 text-end"><button class="btn btn-primary">{{ __('Save') }}</button></div>
</form>
@endsection
