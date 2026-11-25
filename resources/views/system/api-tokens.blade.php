@extends('layouts.app')
@section('title', __('API Tokens'))
@section('content')
<x-page-header :title="__('API Tokens')" icon="bi-key" :breadcrumbs="[__('Administration'), __('API Tokens')]" :subtitle="__('REST API base URL').': <code>'.url('/api/v1').'</code> · '.__('See docs/API.md')"/>
@if(session('plain_token'))<div class="alert alert-warning"><b>{{ __('New token') }}:</b> <code class="user-select-all">{{ session('plain_token') }}</code></div>@endif
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><table class="table table-erp mb-0">
        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Abilities') }}</th><th>{{ __('Last used') }}</th><th>{{ __('Expires') }}</th><th></th></tr></thead>
        <tbody>@forelse($tokens as $t)<tr><td>{{ $t->name }}</td><td>{{ implode(', ', $t->abilities) }}</td><td>{{ $t->last_used_at?->diffForHumans() ?? '—' }}</td><td>{{ $t->expires_at?->format('Y-m-d') }}</td>
            <td><form method="post" action="{{ route('api-tokens.destroy', $t->id) }}" data-confirm="{{ __('Revoke this token?') }}">@csrf @method('delete')<button class="btn btn-sm btn-outline-danger">{{ __('Revoke') }}</button></form></td></tr>
        @empty<tr><td colspan="5" class="text-body-secondary">—</td></tr>@endforelse</tbody>
    </table></div></div>
    <div class="col-lg-4"><form method="post" action="{{ route('api-tokens.store') }}" class="card">@csrf<div class="card-header">{{ __('Create token') }}</div><div class="card-body">
        <input name="name" class="form-control mb-2" placeholder="{{ __('Token name (e.g. mobile app)') }}" required>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="abilities[]" value="read" checked><label class="form-check-label">read</label></div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="abilities[]" value="write"><label class="form-check-label">write</label></div>
        <button class="btn btn-primary w-100">{{ __('Create') }}</button></div></form></div>
</div>
@endsection
