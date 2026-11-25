@extends('layouts.app')
@section('title', __('Integrations'))
@section('content')
<x-page-header :title="__('Integrations')" icon="bi-plug" :breadcrumbs="[__('Administration'), __('Integrations')]"
    :subtitle="__('All integrations are optional and disabled by default. Credentials are stored encrypted. Email (SMTP) is configured in the server .env file.')"/>
<div class="row g-3">
    @foreach($providers as $key => $p)
        @php $i = $items[$key] ?? null; $creds = $i?->credentials ?? []; @endphp
        <div class="col-lg-6"><form method="post" action="{{ route('integrations.save', $key) }}" class="card h-100">@csrf @method('put')
            <div class="card-header d-flex"><span>{{ __($p['label']) }}</span><span class="ms-auto">{!! status_badge($i?->is_active ? 'active' : 'draft') !!}</span></div>
            <div class="card-body row g-2">
                @foreach($p['fields'] as $f)
                    @php $secret = preg_match('/token|secret|password|key/', $f); @endphp
                    <div class="col-md-6"><label class="form-label small">{{ __(\Illuminate\Support\Str::headline($f)) }}</label>
                        @if($f === 'driver')<select name="credentials[driver]" class="form-select form-select-sm"><option value="sandbox" @selected(($creds['driver'] ?? '') === 'sandbox')>Sandbox (test)</option><option value="thawani" @selected(($creds['driver'] ?? '') === 'thawani')>Thawani Pay</option></select>
                        @elseif($f === 'method')<select name="credentials[method]" class="form-select form-select-sm"><option>POST</option><option @selected(($creds['method'] ?? '') === 'GET')>GET</option></select>
                        @else<input type="{{ $secret ? 'password' : 'text' }}" name="credentials[{{ $f }}]" value="{{ $secret ? '' : ($creds[$f] ?? '') }}" class="form-control form-control-sm" dir="ltr" placeholder="{{ $secret && !empty($creds[$f]) ? '•••••• ('.__('unchanged').')' : '' }}" autocomplete="off">@endif
                    </div>
                @endforeach
                @if($key === 'attendance')<div class="col-12 small text-body-secondary">{{ __('Devices push punches to') }} <code>POST {{ url('/api/v1/attendance/punch') }}</code></div>@endif
                @if($key === 'vin')<div class="col-12 small text-body-secondary">{{ __('Default') }}: https://vpic.nhtsa.dot.gov/api</div>@endif
            </div>
            <div class="card-footer d-flex"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($i?->is_active)><label class="form-check-label">{{ __('Enabled') }}</label></div>
                @can('integrations.update')<button class="btn btn-sm btn-primary ms-auto">{{ __('Save') }}</button>@endcan</div>
        </form></div>
    @endforeach
</div>
@endsection
