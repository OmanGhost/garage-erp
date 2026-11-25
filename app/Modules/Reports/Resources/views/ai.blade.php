@extends('layouts.app')
@section('title', __('AI Assistant'))
@section('content')
<x-page-header :title="__('AI Assistant')" icon="bi-stars" :breadcrumbs="[__('AI Assistant')]"
    :subtitle="__('Learns from your own workshop history: diagnosis suggestions and spare parts demand forecasting. Runs locally, no external paid service.')"/>
<div class="row g-3">
    <div class="col-xl-5">
        <form method="post" action="{{ route('ai.diagnose') }}" class="card mb-3">@csrf
            <div class="card-header"><i class="bi bi-activity me-1"></i>{{ __('Diagnosis assistant') }}</div>
            <div class="card-body row g-2">
                <div class="col-12"><label class="form-label">{{ __('Symptom / complaint') }}</label><textarea name="symptom" class="form-control" rows="3" placeholder="{{ __('e.g. engine vibration at idle, A/C not cooling...') }}">{{ old('symptom') }}</textarea></div>
                <div class="col-6"><label class="form-label">{{ __('Fault code (DTC)') }}</label><input name="dtc" class="form-control text-uppercase" value="{{ old('dtc') }}" placeholder="P0301"></div>
                <div class="col-6"><label class="form-label">{{ __('Make') }}</label><select name="make_id" class="form-select"><option value="">{{ __('Any') }}</option>@foreach($makes as $id => $n)<option value="{{ $id }}" @selected(old('make_id') == $id)>{{ $n }}</option>@endforeach</select></div>
                <div class="col-12"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> {{ __('Analyse history') }}</button></div>
            </div>
        </form>
        @if($result)
        <div class="card"><div class="card-header">{{ __('Results') }} — {{ $result['matches'] }} {{ __('similar jobs') }} <span class="badge text-bg-{{ ['high' => 'success', 'medium' => 'warning', 'low' => 'secondary'][$result['confidence']] }}">{{ __('Confidence') }}: {{ status_label($result['confidence']) }}</span></div>
            <div class="card-body small">
                <h6>{{ __('Most likely repairs') }}</h6>
                <ol>@forelse($result['services'] as $s)<li>{{ $s['description'] }} — {{ $s['n'] }}× · ~{{ round($s['minutes']) }} {{ __('min') }} · ~{{ money($s['price']) }}</li>@empty<li class="text-body-secondary">—</li>@endforelse</ol>
                <h6>{{ __('Parts commonly replaced') }}</h6>
                <ol>@forelse($result['parts'] as $p)<li><a href="{{ route('parts.show', $p['id']) }}">{{ $p['sku'] }}</a> {{ $p['name_ar'] }} — {{ $p['n'] }}×</li>@empty<li class="text-body-secondary">—</li>@endforelse</ol>
                @if(!empty($result['diagnoses']))<h6>{{ __('Previous diagnoses') }}</h6><ul>@foreach($result['diagnoses'] as $d => $n)<li>{{ $d }} ({{ $n }})</li>@endforeach</ul>@endif
            </div></div>
        @endif
    </div>
    <div class="col-xl-7"><div class="card"><div class="card-header"><i class="bi bi-graph-up-arrow me-1"></i>{{ __('Spare parts demand forecast (next month)') }}</div>
        <div class="table-responsive"><table class="table table-sm table-erp mb-0">
            <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Last 6 months') }}</th><th>{{ __('Trend') }}</th><th>{{ __('Forecast') }}</th><th>{{ __('Available') }}</th><th></th></tr></thead>
            <tbody>@forelse($forecast as $r)<tr><td><a href="{{ route('parts.show', $r['part']) }}">{{ $r['part']->sku }}</a> {{ \Illuminate\Support\Str::limit($r['part']->displayName(), 30) }}</td>
                <td class="small font-monospace">{{ implode(' · ', array_map(fn($v) => qty($v), $r['history'])) }}</td>
                <td><i class="bi bi-arrow-{{ $r['trend'] === 'up' ? 'up-right text-success' : 'down-right text-danger' }}"></i></td>
                <td class="fw-bold">{{ qty($r['forecast']) }}</td><td class="{{ (float) $r['available'] < $r['forecast'] ? 'text-danger fw-bold' : '' }}">{{ qty($r['available']) }}</td>
                <td>@if((float) $r['available'] < $r['forecast'])<span class="badge text-bg-warning">{{ __('Reorder') }}</span>@endif</td></tr>
            @empty<tr><td colspan="6"><div class="empty-state"><i class="bi bi-graph-up"></i>{{ __('Not enough sales history yet.') }}</div></td></tr>@endforelse</tbody>
        </table></div>
        <div class="card-footer text-end"><a href="{{ route('inventory.reorder') }}" class="btn btn-sm btn-outline-primary">{{ __('Open reorder suggestions') }}</a></div>
    </div></div>
</div>
@endsection
