@extends('layouts.app')
@section('title', __($role->name))
@section('content')
<x-page-header :title="__('Permissions').' — '.__($role->name)" icon="bi-shield-lock" :breadcrumbs="[__('Roles & Permissions') => route('roles.index'), __($role->name)]"/>
<form method="post" action="{{ route('roles.update', $role) }}" class="card">@csrf @method('put')
    <div class="card-body">
        <input type="search" class="form-control mb-3" placeholder="{{ __('Filter...') }}" oninput="document.querySelectorAll('[data-perm-row]').forEach(r => r.style.display = r.dataset.permRow.includes(this.value.toLowerCase()) ? '' : 'none')">
        <div class="table-responsive"><table class="table table-sm align-middle">
            <thead class="table-light"><tr><th>{{ __('Resource') }}</th>@foreach(['view', 'create', 'update', 'delete', 'approve', 'print', 'export'] as $a)<th class="text-center">{{ __(ucfirst($a)) }}</th>@endforeach<th>{{ __('Other') }}</th></tr></thead>
            <tbody>@foreach($grouped as $res => $perms)
                <tr data-perm-row="{{ strtolower($res.' '.__(\Illuminate\Support\Str::headline($res))) }}"><td>{{ __(\Illuminate\Support\Str::headline($res)) }}</td>
                    @foreach(['view', 'create', 'update', 'delete', 'approve', 'print', 'export'] as $a)<td class="text-center">@if($perms->contains("$res.$a"))<input type="checkbox" class="form-check-input" name="permissions[]" value="{{ "$res.$a" }}" @checked(in_array("$res.$a", $has))>@endif</td>@endforeach
                    <td>@foreach($perms->reject(fn($p) => in_array(explode('.', $p)[1], ['view', 'create', 'update', 'delete', 'approve', 'print', 'export'])) as $p)<label class="me-2 small"><input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $p }}" @checked(in_array($p, $has))> {{ __(\Illuminate\Support\Str::headline(explode('.', $p)[1])) }}</label>@endforeach</td></tr>
            @endforeach</tbody>
        </table></div>
    </div>
    <div class="card-footer text-end">@can('roles.update')<button class="btn btn-primary">{{ __('Save permissions') }}</button>@endcan</div>
</form>
@endsection
