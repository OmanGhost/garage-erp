@extends('layouts.app')
@section('title', __('Inspection'))
@section('content')
@php
    $jc = $inspection->jobCard;
    $open = $inspection->status !== 'completed';
    $canEdit = $open && auth()->user()->can('inspections.update');
    $lights = $inspection->zoneLights();
    $counts = $inspection->lightCounts();
    $Z = \App\Modules\Workshop\Models\Inspection::class;
    $resColor = ['ok' => 'success', 'attention' => 'warning', 'urgent' => 'danger', 'na' => 'secondary'];
    $sevLight = fn ($s) => $Z::light($s);
    $approvedTotal = $inspection->findings->where('customer_decision', 'approved')->sum(fn ($f) => (float) $f->estimated_price * (float) $f->qty);
    $dviLink = session('dvi_link') ?? $inspection->publicUrl();
@endphp
<x-page-header :title="__('Digital vehicle inspection')" icon="bi-clipboard2-pulse" :breadcrumbs="[__('Job Cards') => route('crud.index', 'job-cards'), $jc->number => route('job-cards.show', $jc), __('Inspection')]"
    :subtitle="e($jc->vehicle->label()).' · '.e($jc->customer->name).' · '.($inspection->stage === 'after' ? __('After repair') : __('Before repair')).' · '.status_badge($inspection->status)">
    <a href="{{ route('inspections.print', $inspection) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print report') }}</a>
    @can('inspections.update')
        <form method="post" action="{{ route('inspections.send', $inspection) }}">@csrf<button class="btn btn-outline-primary"><i class="bi bi-send"></i> {{ $inspection->sent_at ? __('Send again') : __('Send report to customer') }}</button></form>
    @endcan
    @if($open)
        @can('inspections.update')<form method="post" action="{{ route('inspections.complete', $inspection) }}" data-confirm="{{ __('Complete the inspection?') }}">@csrf <x-idem/><button class="btn btn-success"><i class="bi bi-check2-all"></i> {{ __('Complete inspection') }}</button></form>@endcan
    @endif
    @can('quotations.create')
        @if($inspection->findings->isNotEmpty() && $inspection->stage === 'before')<form method="post" action="{{ route('inspections.quotation', $inspection) }}">@csrf <x-idem/><button class="btn btn-primary"><i class="bi bi-file-earmark-text"></i> {{ __('Create quotation from findings') }}</button></form>@endif
    @endcan
</x-page-header>

@if($dviLink)
    <div class="alert alert-info d-flex flex-wrap align-items-center gap-2 py-2">
        <i class="bi bi-link-45deg fs-5"></i>
        <span>{{ __('Interactive report for the customer') }}:</span>
        <input class="form-control form-control-sm font-monospace flex-grow-1" style="max-width:520px" readonly value="{{ $dviLink }}" onclick="this.select()" aria-label="{{ __('Report link') }}">
        <a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D/', '', (string) omani_phone($jc->customer->phone)) }}?text={{ rawurlencode(__('Inspection report of your vehicle :p', ['p' => $jc->vehicle->plate()]).': '.$dviLink) }}"><i class="bi bi-whatsapp"></i> WhatsApp</a>
        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="{{ $dviLink }}"><i class="bi bi-box-arrow-up-right"></i> {{ __('Open') }}</a>
        @if($inspection->customer_viewed_at)<span class="small text-success ms-auto"><i class="bi bi-eye"></i> {{ __('Seen by customer') }} {{ $inspection->customer_viewed_at->format('Y-m-d H:i') }}</span>@endif
    </div>
@endif

<div class="row g-3" x-data="{ zone: '', pick(z) { this.zone = z; this.$nextTick(() => { const f = document.getElementById('findingForm'); f && f.scrollIntoView({ behavior: 'smooth', block: 'center' }); document.getElementById('f_desc')?.focus(); }); } }" @zone-picked="pick($event.detail.zone)">
    <div class="col-xl-7">
        {{-- traffic lights summary --}}
        <div class="light-counter mb-3">
            <div class="g"><span class="fs-4">{{ $counts['green'] }}</span><div class="small fw-normal">{{ __('Good') }}</div></div>
            <div class="y"><span class="fs-4">{{ $counts['yellow'] }}</span><div class="small fw-normal">{{ __('Needs attention soon') }}</div></div>
            <div class="r"><span class="fs-4">{{ $counts['red'] }}</span><div class="small fw-normal">{{ __('Urgent') }}</div></div>
        </div>

        @if($canEdit)<form id="suggestForm" method="post" action="{{ route('inspections.suggest', $inspection) }}">@csrf</form>@endif
        <form method="post" action="{{ route('inspections.update', $inspection) }}" enctype="multipart/form-data" class="card mb-3">@csrf @method('put')
            <div class="card-header d-flex align-items-center"><i class="bi bi-list-check me-1"></i>{{ __('Checklist & measurements') }} @if($inspection->template)<span class="text-body-secondary small ms-1">— {{ $inspection->template->name }}</span>@endif
                @if($canEdit)<button form="suggestForm" class="btn btn-sm btn-outline-primary ms-auto" title="{{ __('Add the services linked to yellow / red items as findings') }}"><i class="bi bi-magic"></i> {{ __('Suggest services') }}</button>@endif</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                @forelse($inspection->results->groupBy('section') as $section => $rows)
                    <tr class="table-light"><th colspan="4">{{ $section }}</th></tr>
                    @foreach($rows as $r)
                        <tr>
                            <td style="width:34%"><span class="light-dot light-{{ $Z::light($r->result) }} me-1"></span>{{ $r->label }}
                                @if($r->templateItem && ($r->templateItem->min_ok !== null || $r->templateItem->max_ok !== null))<div class="small text-body-secondary">{{ __('OK range') }}: {{ $r->templateItem->min_ok !== null ? rtrim(rtrim((string) $r->templateItem->min_ok, '0'), '.') : '…' }} – {{ $r->templateItem->max_ok !== null ? rtrim(rtrim((string) $r->templateItem->max_ok, '0'), '.') : '…' }} {{ $r->unit }}</div>@endif</td>
                            <td style="width:170px">
                                <div class="btn-group btn-group-sm" role="group" aria-label="{{ $r->label }}">
                                    @foreach(['ok' => ['success', 'bi-check-lg'], 'attention' => ['warning', 'bi-exclamation-lg'], 'urgent' => ['danger', 'bi-x-lg'], 'na' => ['secondary', 'bi-dash']] as $val => [$c, $ic])
                                        <input type="radio" class="btn-check" name="results[{{ $r->id }}][result]" id="r{{ $r->id }}{{ $val }}" value="{{ $val }}" @checked($r->result === $val) @disabled(! $canEdit)>
                                        <label class="btn btn-outline-{{ $c }}" for="r{{ $r->id }}{{ $val }}" title="{{ status_label($val) }}"><i class="bi {{ $ic }}"></i></label>
                                    @endforeach
                                </div>
                            </td>
                            <td style="width:120px">@if($r->unit || $r->value !== null)<div class="input-group input-group-sm"><input type="number" step="0.001" name="results[{{ $r->id }}][value]" value="{{ $r->value !== null ? rtrim(rtrim((string) $r->value, '0'), '.') : '' }}" class="form-control" aria-label="{{ __('Measurement') }}" @disabled(! $canEdit)>@if($r->unit)<span class="input-group-text">{{ $r->unit }}</span>@endif</div>@endif</td>
                            <td><input name="results[{{ $r->id }}][notes]" value="{{ $r->notes }}" class="form-control form-control-sm" aria-label="{{ __('Notes') }}" @disabled(! $canEdit)></td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td class="text-body-secondary small p-3">{{ __('No checklist: findings can still be added on the diagram.') }}</td></tr>
                @endforelse
            </table></div>
            <div class="card-body border-top">
                <label class="form-label" for="i_sum">{{ __('Summary') }}</label><textarea id="i_sum" name="summary" class="form-control mb-2" rows="2" @disabled(! $canEdit)>{{ $inspection->summary }}</textarea>
                <label class="form-label" for="i_rec">{{ __('Technician recommendations') }}</label><textarea id="i_rec" name="recommendations" class="form-control mb-2" rows="2" @disabled(! $canEdit)>{{ $inspection->recommendations }}</textarea>
                @if($canEdit)
                    <label class="form-label" for="i_files">{{ __('Photos, videos & scanner reports') }}</label><input id="i_files" type="file" name="files[]" multiple class="form-control mb-2" accept="image/*,video/*,.pdf" capture="environment">
                    <button class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save') }}</button>
                @endif
            </div>
        </form>
        <x-attachments :model="$inspection" :title="__('Inspection photos & scanner reports')" :can-upload="$open"/>
    </div>

    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center"><i class="bi bi-car-front me-1"></i>{{ __('Vehicle diagram') }}
                <span class="small text-body-secondary ms-auto">@if($canEdit){{ __('Tap an area to record a fault') }}@endif</span></div>
            <div class="card-body">
                @include('workshop::partials.dvi-diagram', ['lights' => $lights, 'interactive' => $canEdit])
                <div class="d-flex justify-content-center gap-3 small mt-2"><span><span class="light-dot light-green"></span> {{ __('Good') }}</span><span><span class="light-dot light-yellow"></span> {{ __('Soon') }}</span><span><span class="light-dot light-red"></span> {{ __('Urgent') }}</span></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center"><i class="bi bi-bug me-1"></i>{{ __('Findings & recommended repairs') }}
                @if(\App\Support\Money::isPositive($approvedTotal))<span class="badge text-bg-success ms-auto">{{ __('Approved') }}: {{ money($approvedTotal, false) }}</span>@endif</div>
            <div class="list-group list-group-flush">
                @forelse($inspection->findings as $f)
                    <div class="list-group-item finding-card light-border-{{ $f->light() }}" id="f{{ $f->id }}">
                        <div class="d-flex gap-2 align-items-start">
                            <span class="light-dot light-{{ $f->light() }} mt-1"></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold">{{ $f->description }} @if($f->dtc_code)<code>{{ $f->dtc_code }}</code>@endif</div>
                                <div class="small text-body-secondary">
                                    @if($f->zone)<span class="badge text-bg-light"><i class="bi bi-geo"></i> {{ $Z::zoneLabel($f->zone) }}</span>@endif
                                    {{ status_label($f->severity) }}
                                    @if($f->service) · {{ $f->service->displayName() }}@endif
                                    @if($f->part) · {{ $f->part->label() }} × {{ qty($f->qty) }}@endif
                                    @if(\App\Support\Money::isPositive($f->estimated_price)) · <b class="font-monospace">{{ money($f->estimated_price, false) }}</b>@endif
                                    @if($f->notes) · {{ $f->notes }}@endif
                                </div>
                                @if($f->attachments->isNotEmpty())<div class="d-flex flex-wrap gap-1 mt-1">@foreach($f->attachments->filter->isImage() as $a)<a href="{{ $a->url() }}" target="_blank" rel="noopener"><img src="{{ $a->url() }}" class="dvi-thumb" alt="{{ $f->description }}" loading="lazy"></a>@endforeach</div>@endif
                                <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                    @if($f->customer_decision)
                                        <span class="badge {{ $f->customer_decision === 'approved' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $f->customer_decision === 'approved' ? __('Customer approved') : __('Customer declined') }}</span>
                                    @endif
                                    @can('inspections.update')
                                        @if(! $jc->isClosed())
                                            <form method="post" action="{{ route('inspections.findings.decide', $f) }}" class="d-inline">@csrf<input type="hidden" name="decision" value="approved"><button class="btn btn-sm btn-link p-0 text-success" title="{{ __('Customer approved (in person / phone)') }}"><i class="bi bi-hand-thumbs-up"></i></button></form>
                                            <form method="post" action="{{ route('inspections.findings.decide', $f) }}" class="d-inline">@csrf<input type="hidden" name="decision" value="declined"><button class="btn btn-sm btn-link p-0 text-secondary" title="{{ __('Customer declined') }}"><i class="bi bi-hand-thumbs-down"></i></button></form>
                                        @endif
                                    @endcan
                                </div>
                            </div>
                            @if($canEdit)<form method="post" action="{{ route('inspections.findings.destroy', $f) }}">@csrf @method('delete')<button class="btn btn-sm btn-link text-danger p-0" aria-label="{{ __('Delete') }}"><i class="bi bi-x-lg"></i></button></form>@endif
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-body-secondary small">{{ __('No findings recorded.') }}</div>
                @endforelse
            </div>
            @if($canEdit)
            <form method="post" action="{{ route('inspections.findings.store', $inspection) }}" enctype="multipart/form-data" class="card-body border-top row g-2" id="findingForm">@csrf
                <div class="col-12"><label class="form-label small mb-0" for="f_zone">{{ __('Area of the vehicle') }}</label>
                    <select id="f_zone" name="zone" class="form-select form-select-sm" x-model="zone"><option value="">—</option>@foreach($Z::ZONES as $k => [$ar, $en])<option value="{{ $k }}">{{ app()->getLocale() === 'ar' ? $ar : $en }}</option>@endforeach</select></div>
                <div class="col-8"><input id="f_desc" name="description" class="form-control form-control-sm" placeholder="{{ __('Fault description') }}" aria-label="{{ __('Fault description') }}" required></div>
                <div class="col-4"><input name="dtc_code" class="form-control form-control-sm text-uppercase" placeholder="P0301" maxlength="5" aria-label="DTC"></div>
                <div class="col-12">
                    <div class="btn-group btn-group-sm w-100" role="group" aria-label="{{ __('Priority') }}">
                        @foreach(['low' => ['success', __('Good / note')], 'medium' => ['warning', __('Soon')], 'high' => ['danger', __('Urgent')], 'critical' => ['danger', __('Safety critical')]] as $s => [$c, $l])
                            <input type="radio" class="btn-check" name="severity" id="sev{{ $s }}" value="{{ $s }}" @checked($s === 'medium')><label class="btn btn-outline-{{ $c }}" for="sev{{ $s }}">{{ $l }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="col-12"><x-lookup name="service_id" type="services" size="sm" :placeholder="__('Required service (optional)')" :params="'vehicle_type='.$jc->vehicle->vehicle_type"/></div>
                <div class="col-9"><x-lookup name="part_id" type="parts" size="sm" :placeholder="__('Required part (optional)')"/></div>
                <div class="col-3"><input name="qty" type="number" step="0.001" min="0.001" value="1" class="form-control form-control-sm" aria-label="{{ __('Qty') }}"></div>
                <div class="col-6"><input name="estimated_price" type="number" step="0.001" min="0" class="form-control form-control-sm" placeholder="{{ __('Estimated price') }}" aria-label="{{ __('Estimated price') }}"></div>
                <div class="col-6"><input name="notes" class="form-control form-control-sm" placeholder="{{ __('Notes') }}" aria-label="{{ __('Notes') }}"></div>
                <div class="col-12"><label class="form-label small mb-0" for="f_ph"><i class="bi bi-camera"></i> {{ __('Photos of the fault') }}</label><input id="f_ph" type="file" name="photos[]" multiple accept="image/*" capture="environment" class="form-control form-control-sm"></div>
                <div class="col-12"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus"></i> {{ __('Add finding') }}</button></div>
            </form>
            @endif
        </div>

        {{-- signatures --}}
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-pen me-1"></i>{{ __('Signatures') }}</div>
            <div class="card-body row g-3">
                @foreach(['technician' => [__('Technician'), $inspection->technician_signature, $inspection->technician?->name, $inspection->technician_signed_at], 'customer' => [__('Customer'), $inspection->customer_signature, $inspection->customer_signed_name, $inspection->customer_signed_at]] as $who => [$label, $sig, $name, $at])
                    <div class="col-sm-6">
                        <div class="small fw-semibold mb-1">{{ $label }}</div>
                        @if($sig)
                            <img src="{{ \App\Modules\Workshop\Services\InspectionService::signatureData($sig) }}" alt="{{ $label }}" class="border rounded bg-white" style="max-height:80px;max-width:100%">
                            <div class="small text-body-secondary">{{ $name }} · {{ $at?->format('Y-m-d H:i') }}</div>
                        @else
                            @can('inspections.update')
                                @include('workshop::partials.signature-form', ['action' => route('inspections.sign', $inspection), 'who' => $who, 'askName' => $who === 'customer', 'name' => $who === 'customer' ? $jc->customer->name : null])
                            @else<span class="text-body-secondary small">—</span>@endcan
                        @endif
                    </div>
                @endforeach
            </div>
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
