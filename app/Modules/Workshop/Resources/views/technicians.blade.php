@extends('layouts.app')
@section('title', __('Technicians'))
@section('content')
<x-page-header :title="__('Technicians').' — '.$department->displayName()" icon="bi-people" :breadcrumbs="[__('Workshop Departments') => route('crud.index', 'departments'), $department->displayName()]"/>
<form method="post" class="card">@csrf @method('put')
    <div class="card-body row">
        @foreach($users as $u)
            <div class="col-md-4 col-lg-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="users[]" value="{{ $u->id }}" id="u{{ $u->id }}" @checked($department->technicians->contains($u))><label class="form-check-label" for="u{{ $u->id }}">{{ $u->name }}</label></div></div>
        @endforeach
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Save') }}</button></div>
</form>
@endsection
