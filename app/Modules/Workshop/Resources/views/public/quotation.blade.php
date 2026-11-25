@extends('layouts.public')
@section('title', __('Quotation').' '.$q->number)
@section('content')
<div class="card mx-auto" style="max-width:900px">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <h5 class="mb-0">{{ __('Quotation') }} {{ $q->number }} <small class="text-body-secondary">v{{ $q->version }}</small></h5>
        <span class="ms-auto">{!! status_badge($q->isExpired() ? 'expired' : $q->status) !!}</span>
    </div>
    <div class="card-body">
        <div class="row small mb-3">
            <div class="col-md-6"><b>{{ __('Customer') }}:</b> {{ $q->customer->name }}</div>
            @if($q->vehicle)<div class="col-md-6"><b>{{ __('Vehicle') }}:</b> {{ $q->vehicle->label() }}</div>@endif
            <div class="col-md-6"><b>{{ __('Valid until') }}:</b> {{ $q->valid_until?->format('Y-m-d') }}</div>
        </div>
        @php $canRespond = in_array($q->status, ['draft', 'sent']) && ! $q->isExpired(); @endphp
        <form method="post" action="{{ route('public.quotation.respond', $q->access_token) }}">@csrf
            <div class="table-responsive"><table class="table align-middle">
                <thead class="table-light"><tr><th>{{ __('Item') }}</th><th>{{ __('Qty') }}</th><th class="text-end">{{ __('Total incl. VAT') }}</th><th>{{ __('Approve?') }}</th></tr></thead>
                <tbody>
                @foreach($q->items as $it)
                    <tr>
                        <td>{{ $it->description }} @if($it->option_group)<span class="badge text-bg-info">{{ __('Option') }} {{ $it->option_group }}</span>@endif @if($it->part_condition === 'used')<span class="badge text-bg-warning">{{ __('Used part') }}</span>@endif</td>
                        <td>{{ qty($it->qty) }}</td>
                        <td class="text-end font-monospace">{{ money((float) $it->line_total + (float) $it->tax_amount) }}</td>
                        <td>
                            @if($canRespond)
                                <div class="form-check form-switch"><input type="hidden" name="decisions[{{ $it->id }}]" value="rejected"><input class="form-check-input" type="checkbox" name="decisions[{{ $it->id }}]" value="approved" checked></div>
                            @else {!! status_badge($it->approval_status) !!} @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr class="fw-bold"><td colspan="2">{{ __('Total') }}</td><td class="text-end font-monospace">{{ money($q->total) }}</td><td></td></tr></tfoot>
            </table></div>
            @if($q->terms)<div class="alert alert-light small">{!! nl2br(e($q->terms)) !!}</div>@endif
            @if($canRespond)
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label">{{ __('Your name') }}</label><input name="name" class="form-control" value="{{ $q->customer->name }}" required></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control"></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="accept_terms" value="1" id="acc" required><label class="form-check-label" for="acc">{{ __('I approve the selected items and authorize the work.') }}</label></div></div>
                    <div class="col-12"><button class="btn btn-success btn-lg w-100"><i class="bi bi-check2-circle"></i> {{ __('Submit my decision') }}</button></div>
                </div>
            @endif
        </form>
    </div>
</div>
@endsection
