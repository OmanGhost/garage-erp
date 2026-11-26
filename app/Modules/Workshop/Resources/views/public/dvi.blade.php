@extends('layouts.public')
@section('title', __('Vehicle inspection report'))
@section('content')
@php
    $jc = $i->jobCard;
    $Z = \App\Modules\Workshop\Models\Inspection::class;
    $counts = $i->lightCounts();
    $closed = $jc->isClosed();
    $photo = fn ($a) => route('public.dvi.photo', [$token, $a->id]);
    $recommended = $i->findings->sortByDesc(fn ($f) => ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1][$f->severity] ?? 0);
    $approved = $i->findings->where('customer_decision', 'approved')->sum(fn ($f) => (float) $f->estimated_price * (float) $f->qty);
@endphp
<div class="mx-auto" style="max-width:860px">
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="doc-hero-icon bg-primary-subtle text-primary"><i class="bi bi-clipboard2-pulse"></i></span>
            <div class="flex-grow-1">
                <h1 class="h4 mb-0">{{ __('Vehicle inspection report') }}</h1>
                <div class="text-body-secondary">{{ $jc->vehicle->label() }} · {{ __('Job card') }} {{ $jc->number }}</div>
                <div class="small text-body-secondary">{{ $i->completed_at?->format('Y-m-d H:i') ?? $i->started_at?->format('Y-m-d H:i') }} · {{ __('Technician') }}: {{ $i->technician?->name }} · {{ $i->stage === 'after' ? __('After repair') : __('Before repair') }}</div>
            </div>
        </div>
    </div>

    <div class="light-counter mb-3">
        <div class="g"><span class="fs-3">{{ $counts['green'] }}</span><div class="small fw-normal">{{ __('Good') }}</div></div>
        <div class="y"><span class="fs-3">{{ $counts['yellow'] }}</span><div class="small fw-normal">{{ __('Needs attention soon') }}</div></div>
        <div class="r"><span class="fs-3">{{ $counts['red'] }}</span><div class="small fw-normal">{{ __('Urgent') }}</div></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-5"><div class="card h-100"><div class="card-body">
            @include('workshop::partials.dvi-diagram', ['lights' => $i->zoneLights(), 'interactive' => false])
        </div></div></div>
        <div class="col-md-7"><div class="card h-100"><div class="card-body">
            @if($i->summary)<h2 class="h6">{{ __('Summary') }}</h2><p style="white-space:pre-line">{{ $i->summary }}</p>@endif
            @if($i->recommendations)<h2 class="h6">{{ __('Technician recommendations') }}</h2><p class="mb-0" style="white-space:pre-line">{{ $i->recommendations }}</p>@endif
            @if(! $i->summary && ! $i->recommendations)<p class="text-body-secondary mb-0">{{ __('The colours on the vehicle show where the technician found something: yellow = needs attention soon, red = urgent.') }}</p>@endif
        </div></div></div>
    </div>

    @if($recommended->isNotEmpty())
        <h2 class="h5 mb-2"><i class="bi bi-tools"></i> {{ __('Recommended repairs') }}</h2>
        <p class="small text-body-secondary">{{ __('Approve the repairs you want us to carry out. We will confirm the final price before starting.') }}</p>
        @foreach($recommended as $f)
            <div class="card mb-2 finding-card light-border-{{ $f->light() }}" id="f{{ $f->id }}">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <span class="light-dot light-{{ $f->light() }} mt-2"></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold">{{ $f->description }}</div>
                            <div class="small text-body-secondary">
                                @if($f->zone){{ $Z::zoneLabel($f->zone) }} · @endif{{ $f->light() === 'red' ? __('Urgent') : ($f->light() === 'yellow' ? __('Needs attention soon') : __('Note')) }}
                                @if($f->service) · {{ $f->service->displayName() }}@endif
                            </div>
                            @if($f->attachments->isNotEmpty())
                                <div class="d-flex flex-wrap gap-2 mt-2">@foreach($f->attachments->filter->isImage() as $a)<a href="{{ $photo($a) }}" target="_blank" rel="noopener"><img src="{{ $photo($a) }}" class="dvi-thumb" style="width:110px;height:84px" alt="{{ $f->description }}" loading="lazy"></a>@endforeach</div>
                            @endif
                        </div>
                        @if(\App\Support\Money::isPositive($f->estimated_price))<div class="fw-bold font-monospace text-nowrap">{{ money((float) $f->estimated_price * (float) $f->qty) }}</div>@endif
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                        @if($f->customer_decision)
                            <span class="badge {{ $f->customer_decision === 'approved' ? 'text-bg-success' : 'text-bg-secondary' }} fs-6">{{ $f->customer_decision === 'approved' ? __('Approved') : __('Not now') }}</span>
                        @endif
                        @unless($closed)
                            <form method="post" action="{{ route('public.dvi.decide', [$token, $f->id]) }}" class="ms-auto d-flex gap-2">@csrf
                                @if($f->customer_decision !== 'approved')<button name="decision" value="approved" class="btn btn-success"><i class="bi bi-check-lg"></i> {{ __('Approve') }}</button>@endif
                                @if($f->customer_decision !== 'declined')<button name="decision" value="declined" class="btn btn-outline-secondary">{{ __('Not now') }}</button>@endif
                            </form>
                        @endunless
                    </div>
                </div>
            </div>
        @endforeach
        @if($approved > 0)<div class="alert alert-success d-flex"><span>{{ __('Approved repairs (estimate)') }}</span><b class="ms-auto font-monospace">{{ money($approved) }}</b></div>@endif
    @endif

    @if($i->results->isNotEmpty())
        <h2 class="h5 mt-4 mb-2"><i class="bi bi-list-check"></i> {{ __('Checklist') }}</h2>
        <div class="card mb-3"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
            @foreach($i->results->groupBy('section') as $section => $rows)
                <tr class="table-light"><th colspan="3">{{ $section }}</th></tr>
                @foreach($rows as $r)
                    <tr><td><span class="light-dot light-{{ $Z::light($r->result) }} me-1"></span>{{ $r->label }}</td>
                        <td class="text-nowrap">@if($r->value !== null){{ rtrim(rtrim((string) $r->value, '0'), '.') }} {{ $r->unit }}@endif</td>
                        <td class="small text-body-secondary">{{ $r->notes }}</td></tr>
                @endforeach
            @endforeach
        </table></div></div>
    @endif

    @php $gallery = $i->attachments->filter->isImage(); @endphp
    @if($gallery->isNotEmpty())
        <h2 class="h5 mt-4 mb-2"><i class="bi bi-images"></i> {{ __('Photos') }}</h2>
        <div class="d-flex flex-wrap gap-2 mb-3">@foreach($gallery as $a)<a href="{{ $photo($a) }}" target="_blank" rel="noopener"><img src="{{ $photo($a) }}" class="dvi-thumb" style="width:140px;height:105px" alt="" loading="lazy"></a>@endforeach</div>
    @endif

    <div class="card mb-4"><div class="card-body">
        <h2 class="h6"><i class="bi bi-pen"></i> {{ __('Customer signature') }}</h2>
        @if($i->customer_signature)
            <img src="{{ \App\Modules\Workshop\Services\InspectionService::signatureData($i->customer_signature) }}" alt="{{ __('Customer signature') }}" class="border rounded bg-white" style="max-height:90px">
            <div class="small text-body-secondary">{{ $i->customer_signed_name }} · {{ $i->customer_signed_at?->format('Y-m-d H:i') }}</div>
        @elseif(! $closed)
            <p class="small text-body-secondary">{{ __('Sign to confirm you have seen this report and your choices above.') }}</p>
            @include('workshop::partials.signature-form', ['action' => route('public.dvi.sign', $token), 'who' => 'customer', 'askName' => true, 'name' => $jc->customer->name])
        @endif
    </div></div>
</div>
@endsection
