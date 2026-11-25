@extends('layouts.portal')
@section('title', $q->number)
@section('content')
<h5>{{ __('Quotation') }} {{ $q->number }} {!! status_badge($q->status) !!}</h5>
<div class="small text-body-secondary mb-2">{{ $q->vehicle?->label() }} · {{ __('Valid until') }} {{ $q->valid_until?->format('Y-m-d') }}</div>
@php $canRespond = $q->status === 'sent' && ! $q->isExpired(); @endphp
<form method="post" action="{{ route('portal.quotations.respond', $q) }}" class="card">@csrf
    <ul class="list-group list-group-flush">
        @foreach($q->items as $it)
            <li class="list-group-item d-flex align-items-center gap-2">
                <div class="flex-grow-1"><div>{{ $it->description }}</div><div class="small text-body-secondary">{{ qty($it->qty) }} × {{ money($it->unit_price, false) }} @if($it->option_group)· {{ __('Option') }} {{ $it->option_group }}@endif @if($it->part_condition === 'used')· {{ __('Used part') }}@endif</div></div>
                <b class="font-monospace">{{ money((float) $it->line_total + (float) $it->tax_amount, false) }}</b>
                @if($canRespond)<div class="form-check form-switch m-0"><input type="hidden" name="decisions[{{ $it->id }}]" value="rejected"><input class="form-check-input" type="checkbox" name="decisions[{{ $it->id }}]" value="approved" checked></div>
                @else {!! status_badge($it->approval_status) !!} @endif
            </li>
        @endforeach
        <li class="list-group-item d-flex fw-bold"><span>{{ __('Total incl. VAT') }}</span><span class="ms-auto font-monospace">{{ money($q->total) }}</span></li>
    </ul>
    @if($canRespond)<div class="card-body"><textarea name="notes" class="form-control mb-2" rows="2" placeholder="{{ __('Notes') }}"></textarea><button class="btn btn-success w-100">{{ __('Submit my decision') }}</button></div>@endif
</form>
@endsection
