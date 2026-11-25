@extends('layouts.portal')
@section('title', $jc->number)
@section('content')
<div class="d-flex align-items-center gap-2 mb-2"><span class="plate-badge">{{ $jc->vehicle->plate() }}</span><b>{{ $jc->vehicle->title() }}</b><span class="ms-auto">{!! status_badge($jc->status) !!}</span></div>
<div class="small text-body-secondary mb-2">{{ $jc->number }} · {{ __('Received') }} {{ $jc->received_at->format('Y-m-d H:i') }} · {{ __('Expected') }} {{ $jc->promised_at?->format('Y-m-d H:i') ?? '—' }}</div>
<div class="progress mb-3" style="height:10px"><div class="progress-bar bg-success" style="width:{{ $jc->progress }}%"></div></div>
@foreach($jc->quotations->where('status', 'sent') as $q)
    <a href="{{ route('portal.quotations.show', $q) }}" class="alert alert-warning d-block">{{ $q->is_supplementary ? __('Additional work needs your approval') : __('Quotation waiting for your approval') }}: {{ $q->number }} — {{ money($q->total) }}</a>
@endforeach
<div class="card mb-3"><div class="card-header">{{ __('Repair stages') }}</div><div class="card-body"><ul class="timeline">
    @foreach($jc->histories->sortBy('id') as $h)<li><b>{{ status_label($h->to_status) }}</b><div class="small text-body-secondary">{{ $h->created_at?->format('Y-m-d H:i') }}</div></li>@endforeach
    @foreach($jc->notes as $n)<li><div>{{ $n->note }}</div><div class="small text-body-secondary">{{ $n->created_at->format('Y-m-d H:i') }}</div></li>@endforeach
</ul></div></div>
<div class="card mb-3"><div class="card-header">{{ __('Work') }}</div><ul class="list-group list-group-flush small">
    @foreach($jc->tasks as $t)<li class="list-group-item d-flex">{{ $t->description }}<span class="ms-auto">{!! status_badge($t->approval_status === 'pending' ? 'pending' : $t->status) !!}</span></li>@endforeach
    @foreach($jc->parts as $p)<li class="list-group-item d-flex">{{ $p->description }} × {{ qty($p->qty) }}<span class="ms-auto">{!! status_badge($p->status) !!}</span></li>@endforeach
</ul></div>
@if($photos->isNotEmpty())
<div class="card mb-3"><div class="card-header">{{ __('Photos') }}</div><div class="card-body d-flex flex-wrap gap-2">
    @foreach($photos as $ph)@if($ph->isImage())<a href="{{ $ph->url() }}" target="_blank"><img src="{{ $ph->url() }}" style="width:96px;height:72px;object-fit:cover" class="rounded border"></a>@endif @endforeach
</div></div>
@endif
@foreach($jc->inspections->where('status', 'completed') as $i)
    <div class="card mb-3"><div class="card-header">{{ __('Inspection report') }}</div><ul class="list-group list-group-flush small">
        @foreach($i->results->whereIn('result', ['attention', 'urgent']) as $r)<li class="list-group-item d-flex">{{ $r->label }}<span class="ms-auto">{!! status_badge($r->result === 'urgent' ? 'critical' : 'pending') !!}</span></li>@endforeach
    </ul></div>
@endforeach
@foreach($jc->invoices->whereIn('status', ['posted', 'partially_paid', 'paid']) as $inv)
    <a href="{{ route('portal.invoices.pdf', $inv) }}" target="_blank" class="btn btn-outline-primary w-100 mb-2"><i class="bi bi-file-pdf"></i> {{ __('Invoice') }} {{ $inv->number }} — {{ money($inv->total) }}</a>
@endforeach
@if(in_array($jc->status, ['delivered', 'completed']))
<form method="post" action="{{ route('portal.jobs.rate', $jc) }}" class="card"><div class="card-header">{{ __('Rate our service') }}</div><div class="card-body">@csrf
    <div class="d-flex gap-2 mb-2 fs-3" x-data="{ r: {{ $jc->rating?->rating ?? 0 }} }"><template x-for="s in 5"><i class="bi cursor-pointer" :class="s <= r ? 'bi-star-fill text-warning' : 'bi-star'" @click="r = s"></i></template><input type="hidden" name="rating" :value="r"></div>
    <textarea name="comment" class="form-control mb-2" rows="2" placeholder="{{ __('Your comments') }}">{{ $jc->rating?->comment }}</textarea>
    <button class="btn btn-primary w-100">{{ __('Submit') }}</button></div></form>
@endif
@endsection
