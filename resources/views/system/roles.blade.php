@extends('layouts.app')
@section('title', __('Roles & Permissions'))
@section('content')
<x-page-header :title="__('Roles & Permissions')" icon="bi-shield-lock" :breadcrumbs="[__('Administration'), __('Roles & Permissions')]"/>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><table class="table table-erp mb-0">
        <thead><tr><th>{{ __('Role') }}</th><th>{{ __('Scope') }}</th><th>{{ __('Permissions') }}</th><th></th></tr></thead>
        <tbody>@foreach($roles as $r)<tr><td>{{ __($r->name) }}</td><td>{!! $r->company_id ? '<span class="badge text-bg-primary">'.__('Custom').'</span>' : '<span class="badge text-bg-secondary">'.__('System template').'</span>' !!}</td>
            <td>{{ in_array($r->name, ['Super Admin', 'Company Admin']) ? __('All') : $r->permissions_count }}</td>
            <td class="text-end">@unless(in_array($r->name, ['Super Admin', 'Company Admin', 'Customer']))<a href="{{ route('roles.edit', $r) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>@endunless</td></tr>@endforeach</tbody>
    </table></div></div>
    @can('roles.create')
    <div class="col-lg-4"><form method="post" action="{{ route('roles.store') }}" class="card">@csrf<div class="card-header">{{ __('New custom role') }}</div><div class="card-body">
        <input name="name" class="form-control mb-2" placeholder="{{ __('Role name') }}" required><button class="btn btn-primary w-100">{{ __('Create') }}</button>
        <div class="form-text">{{ __('Editing a system template creates a company-specific copy.') }}</div></div></form></div>
    @endcan
</div>
@endsection
