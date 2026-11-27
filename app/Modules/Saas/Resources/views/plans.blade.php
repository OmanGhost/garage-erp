@extends('layouts.admin')
@section('title', __('Plans'))
@section('content')
@php
    $modules = config('erp.modules');
    $groups = [
        __('Included in every plan') => collect($modules)->filter(fn ($m) => ! empty($m['core'])),
        __('Main modules') => collect($modules)->filter(fn ($m) => empty($m['core']) && empty($m['requires'])),
        __('Advanced features') => collect($modules)->filter(fn ($m) => ! empty($m['requires'])),
    ];
    $newPlan = new \App\Modules\Core\Models\Plan(['is_active' => true, 'trial_days' => 14, 'modules' => array_keys($modules)]);
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-stars text-brand"></i> {{ __('Subscription plans') }}</h4>
        <div class="small text-body-secondary">{{ __('Choose which modules and features each plan includes. A client can also get its own selection from its file.') }}</div>
    </div>
    <a href="#new-plan" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('New plan') }}</a>
</div>

{{-- comparison --}}
<div class="card mb-4">
    <div class="card-header fw-semibold"><i class="bi bi-table me-1"></i>{{ __('Feature comparison') }}</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0 text-center">
        <thead class="table-light"><tr><th class="text-start">{{ __('Module / feature') }}</th>
            @foreach($plans as $p)<th>{{ $p->name }}<div class="small fw-normal text-body-secondary">{{ money($p->price_monthly) }} / {{ __('month') }} · {{ trans_choice('{0} no clients|{1} 1 client|[2,*] :n clients', $clients[$p->id] ?? 0, ['n' => $clients[$p->id] ?? 0]) }}</div></th>@endforeach</tr></thead>
        <tbody>
        @foreach($groups as $g => $mods)
            <tr class="table-light"><th colspan="{{ $plans->count() + 1 }}" class="text-start small">{{ $g }}</th></tr>
            @foreach($mods as $k => $m)
                <tr><td class="text-start">{{ __($m['label']) }} @if(! empty($m['new']))<span class="badge text-bg-success">{{ __('New') }}</span>@endif
                        @if(! empty($m['description']))<div class="small text-body-secondary">{{ __($m['description']) }}</div>@endif</td>
                    @foreach($plans as $p)
                        <td>@if(! empty($m['core']) || in_array($k, $p->modules ?? [], true))<i class="bi bi-check-circle-fill text-success fs-5" aria-label="{{ __('Included') }}"></i>@else<i class="bi bi-dash text-body-tertiary" aria-label="{{ __('Not included') }}"></i>@endif</td>
                    @endforeach</tr>
            @endforeach
        @endforeach
        </tbody>
    </table></div>
</div>

<div class="row g-3">
    @foreach($plans->push($newPlan) as $p)
    <div class="col-xl-6" @unless($p->id) id="new-plan" @endunless>
        <form method="post" action="{{ route('admin.plans.save') }}" class="card h-100 {{ $p->id ? '' : 'border-primary' }}" x-data>@csrf
            <input type="hidden" name="id" value="{{ $p->id }}">
            <div class="card-header d-flex align-items-center"><span class="fw-semibold">{{ $p->id ? $p->name : __('New plan') }}</span>
                @if($p->id)<span class="ms-auto small text-body-secondary">{{ trans_choice('{0} no clients|{1} 1 client|[2,*] :n clients', $clients[$p->id] ?? 0, ['n' => $clients[$p->id] ?? 0]) }}</span>@endif</div>
            <div class="card-body row g-2">
                <div class="col-3"><label class="form-label small mb-0">{{ __('Code') }}</label><input name="code" value="{{ $p->code }}" class="form-control form-control-sm" required></div>
                <div class="col-3"><label class="form-label small mb-0">{{ __('Name') }}</label><input name="name" value="{{ $p->name }}" class="form-control form-control-sm" required></div>
                <div class="col-4"><label class="form-label small mb-0">{{ __('Name (Arabic)') }}</label><input name="name_ar" value="{{ $p->name_ar }}" class="form-control form-control-sm"></div>
                <div class="col-2"><label class="form-label small mb-0">{{ __('Sort') }}</label><input name="sort" type="number" value="{{ $p->sort }}" class="form-control form-control-sm"></div>
                <div class="col-4"><label class="form-label small mb-0">{{ __('Monthly') }}</label><input name="price_monthly" type="number" step="0.001" value="{{ (float) $p->price_monthly }}" class="form-control form-control-sm" required></div>
                <div class="col-4"><label class="form-label small mb-0">{{ __('Yearly') }}</label><input name="price_yearly" type="number" step="0.001" value="{{ (float) $p->price_yearly }}" class="form-control form-control-sm" required></div>
                <div class="col-4"><label class="form-label small mb-0">{{ __('Trial days') }}</label><input name="trial_days" type="number" value="{{ $p->trial_days }}" class="form-control form-control-sm" required></div>
                <div class="col-3"><label class="form-label small mb-0">{{ __('Users') }}</label><input name="max_users" type="number" value="{{ $p->max_users }}" class="form-control form-control-sm"></div>
                <div class="col-3"><label class="form-label small mb-0">{{ __('Branches') }}</label><input name="max_branches" type="number" value="{{ $p->max_branches }}" class="form-control form-control-sm"></div>
                <div class="col-3"><label class="form-label small mb-0">{{ __('Warehouses') }}</label><input name="max_warehouses" type="number" value="{{ $p->max_warehouses }}" class="form-control form-control-sm"></div>
                <div class="col-3"><label class="form-label small mb-0">MB</label><input name="max_storage_mb" type="number" value="{{ $p->max_storage_mb }}" class="form-control form-control-sm"></div>

                <div class="col-12 mt-2">
                    <div class="d-flex align-items-center mb-1"><span class="fw-semibold small">{{ __('Modules & features') }}</span>
                        <button type="button" class="btn btn-link btn-sm ms-auto p-0" @click="$root.querySelectorAll('.mod-check:not(:disabled)').forEach(c => c.checked = true)">{{ __('Select all') }}</button>
                        <span class="mx-1 text-body-tertiary">|</span>
                        <button type="button" class="btn btn-link btn-sm p-0" @click="$root.querySelectorAll('.mod-check:not(:disabled)').forEach(c => c.checked = false)">{{ __('None') }}</button></div>
                    @foreach($groups as $g => $mods)
                        <div class="small text-body-secondary mt-2 mb-1">{{ $g }}</div>
                        <div class="row g-1">
                            @foreach($mods as $k => $m)
                                <div class="col-md-6">
                                    <label class="module-option {{ ! empty($m['new']) ? 'is-new' : '' }}" for="p{{ $p->id ?: 'new' }}{{ $k }}">
                                        <input class="form-check-input mod-check mt-1" type="checkbox" name="modules[]" value="{{ $k }}" id="p{{ $p->id ?: 'new' }}{{ $k }}"
                                               @checked(! empty($m['core']) || in_array($k, $p->modules ?? [], true)) @disabled(! empty($m['core'])) data-requires="{{ $m['requires'] ?? '' }}"
                                               @change="if ($el.checked && $el.dataset.requires) { const r = $root.querySelector('.mod-check[value=' + $el.dataset.requires + ']'); if (r) r.checked = true; } if (! $el.checked) $root.querySelectorAll('.mod-check[data-requires=' + $el.value + ']').forEach(c => c.checked = false)">
                                        <span><span class="fw-semibold">{{ __($m['label']) }}</span> @if(! empty($m['new']))<span class="badge text-bg-success">{{ __('New') }}</span>@endif
                                            @if(! empty($m['description']))<span class="d-block small text-body-secondary">{{ __($m['description']) }}</span>@endif
                                            @if(! empty($m['requires']))<span class="d-block small text-body-tertiary">{{ __('Requires: :m', ['m' => __($modules[$m['requires']]['label'] ?? $m['requires'])]) }}</span>@endif</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div class="col-7 mt-2"><div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="allow_custom_domain" value="1" id="pd{{ $p->id }}" @checked($p->allow_custom_domain)><label class="form-check-label small" for="pd{{ $p->id }}">{{ __('Custom domain included') }}</label></div></div>
                <div class="col-5 mt-2"><label class="form-label small mb-0" for="pf{{ $p->id }}">{{ __('Domain fee / year') }}</label><input id="pf{{ $p->id }}" name="domain_fee" type="number" step="0.001" min="0" value="{{ (float) $p->domain_fee }}" class="form-control form-control-sm"></div>
                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="pa{{ $p->id }}" @checked($p->is_active)><label class="form-check-label small" for="pa{{ $p->id }}">{{ __('Active') }}</label></div></div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-check2"></i> {{ __('Save') }}</button></div>
        </form>
    </div>
    @endforeach
</div>
@endsection
