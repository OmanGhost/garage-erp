@extends('layouts.app')
@section('title', __('Inspection'))
@section('content')
@php $jc = $inspection->jobCard; $open = $inspection->status !== 'completed'; @endphp
<x-page-header :title="__('Inspection & diagnostics')" icon="bi-search" :breadcrumbs="[__('Job Cards') => route('crud.index', 'job-cards'), $jc->number => route('job-cards.show', $jc), __('Inspection')]"
    :subtitle="e($jc->vehicle->label()).' · '.e($jc->customer->name).' · '.status_badge($inspection->status)">
    <a href="{{ route('inspections.print', $inspection) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print report') }}</a>
    @if($open)
        @can('inspections.update')<form method="post" action="{{ route('inspections.complete', $inspection) }}" data-confirm="{{ __('Complete the inspection?') }}">@csrf <x-idem/><button class="btn btn-success"><i class="bi bi-check2-all"></i> {{ __('Complete inspection') }}</button></form>@endcan
    @endif
    @can('quotations.create')
        @if($inspection->findings->isNotEmpty())<form method="post" action="{{ route('inspections.quotation', $inspection) }}">@csrf <x-idem/><button class="btn btn-primary"><i class="bi bi-file-earmark-text"></i> {{ __('Create quotation from findings') }}</button></form>@endif
    @endcan
</x-page-header>

<div class="row g-3">
    <div class="col-xl-7">
        <form method="post" action="{{ route('inspections.update', $inspection) }}" enctype="multipart/form-data" class="card mb-3">@csrf @method('put')
            <div class="card-header"><i class="bi bi-list-check me-1"></i>{{ __('Checklist') }} @if($inspection->template)<span class="text-body-secondary small">— {{ $inspection->template->name }}</span>@endif</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                @foreach($inspection->results->groupBy('section') as $section => $rows)
                    <tr class="table-light"><th colspan="3">{{ $section }}</th></tr>
                    @foreach($rows as $r)
                        <tr>
                            <td style="width:40%">{{ $r->label }}</td>
                            <td style="width:220px">
                                <div class="btn-group btn-group-sm" role="group">
                                    @foreach(['ok' => ['success', 'bi-check'], 'attention' => ['warning', 'bi-exclamation'], 'urgent' => ['danger', 'bi-x'], 'na' => ['secondary', 'bi-dash']] as $val => [$c, $ic])
                                        <input type="radio" class="btn-check" name="results[{{ $r->id }}][result]" id="r{{ $r->id }}{{ $val }}" value="{{ $val }}" @checked($r->result === $val) @disabled(! $open)>
                                        <label class="btn btn-outline-{{ $c }}" for="r{{ $r->id }}{{ $val }}" title="{{ status_label($val) }}"><i class="bi {{ $ic }}"></i></label>
                                    @endforeach
                                </div>
                            </td>
                            <td><input name="results[{{ $r->id }}][notes]" value="{{ $r->notes }}" class="form-control form-control-sm" @disabled(! $open)></td>
                        </tr>
                    @endforeach
                @endforeach
            </table></div>
            <div class="card-body border-top">
                <label class="form-label">{{ __('Summary') }}</label><textarea name="summary" class="form-control mb-2" rows="2" @disabled(! $open)>{{ $inspection->summary }}</textarea>
                <label class="form-label">{{ __('Technician recommendations') }}</label><textarea name="recommendations" class="form-control mb-2" rows="2" @disabled(! $open)>{{ $inspection->recommendations }}</textarea>
                @if($open)
                    <label class="form-label">{{ __('Photos, videos & scanner reports') }}</label><input type="file" name="files[]" multiple class="form-control mb-2" accept="image/*,video/*,.pdf" capture="environment">
                    <button class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save') }}</button>
                @endif
            </div>
        </form>
        <x-attachments :model="$inspection" :title="__('Inspection photos & scanner reports')" :can-upload="$open"/>
    </div>
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-bug me-1"></i>{{ __('Findings & fault codes (DTC)') }}</div>
            <ul class="list-group list-group-flush">
                @forelse($inspection->findings as $f)
                    <li class="list-group-item">
                        <div class="d-flex gap-2">
                            {!! status_badge($f->severity) !!}
                            @if($f->dtc_code)<code>{{ $f->dtc_code }}</code>@endif
                            <span class="fw-semibold">{{ $f->description }}</span>
                            @if($open)<form method="post" action="{{ route('inspections.findings.destroy', $f) }}" class="ms-auto">@csrf @method('delete')<button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x"></i></button></form>@endif
                        </div>
                        <div class="small text-body-secondary">
                            @if($f->service){{ __('Service') }}: {{ $f->service->displayName() }} · @endif
                            @if($f->part){{ __('Part') }}: {{ $f->part->label() }} × {{ qty($f->qty) }} · @endif
                            {{ $f->notes }}
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary small">{{ __('No findings recorded.') }}</li>
                @endforelse
            </ul>
            @if($open)
            <form method="post" action="{{ route('inspections.findings.store', $inspection) }}" class="card-body border-top row g-2">@csrf
                <div class="col-8"><input name="description" class="form-control form-control-sm" placeholder="{{ __('Fault description') }}" required></div>
                <div class="col-4"><input name="dtc_code" class="form-control form-control-sm text-uppercase" placeholder="P0301" maxlength="5"></div>
                <div class="col-6"><select name="severity" class="form-select form-select-sm">@foreach(['low', 'medium', 'high', 'critical'] as $s)<option value="{{ $s }}" @selected($s === 'medium')>{{ status_label($s) }}</option>@endforeach</select></div>
                <div class="col-6"><input name="estimated_price" type="number" step="0.001" min="0" class="form-control form-control-sm" placeholder="{{ __('Estimated price') }}"></div>
                <div class="col-12"><x-lookup name="service_id" type="services" size="sm" :placeholder="__('Required service (optional)')" :params="'vehicle_type='.$jc->vehicle->vehicle_type"/></div>
                <div class="col-9"><x-lookup name="part_id" type="parts" size="sm" :placeholder="__('Required part (optional)')"/></div>
                <div class="col-3"><input name="qty" type="number" step="0.001" min="0.001" value="1" class="form-control form-control-sm"></div>
                <div class="col-12"><input name="notes" class="form-control form-control-sm" placeholder="{{ __('Notes') }}"></div>
                <div class="col-12"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus"></i> {{ __('Add finding') }}</button></div>
            </form>
            @endif
        </div>
        @if(!empty($suggestions))
        <div class="card mb-3 border-info">
            <div class="card-header"><i class="bi bi-stars me-1 text-info"></i>{{ __('AI assistant — suggestions from history') }}</div>
            <ul class="list-group list-group-flush small">
                @foreach($suggestions as $s)<li class="list-group-item"><b>{{ $s['title'] }}</b> <span class="text-body-secondary">({{ $s['reason'] }})</span></li>@endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection
