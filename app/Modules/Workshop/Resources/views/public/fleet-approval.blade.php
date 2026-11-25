@extends('layouts.public')
@section('title', __('Fleet approval'))
@section('content')
<div class="card mx-auto" style="max-width:720px">
    <div class="card-header"><h5 class="mb-0">{{ __('Maintenance approval request') }} — {{ $a->fleet->name }}</h5></div>
    <div class="card-body">
        <p><b>{{ __('Vehicle') }}:</b> {{ $a->jobCard->vehicle->label() }}<br><b>{{ __('Job card') }}:</b> {{ $a->jobCard->number }}<br><b>{{ __('Estimated amount') }}:</b> {{ money($a->amount) }}</p>
        <ul>@foreach($a->jobCard->tasks as $t)<li>{{ $t->description }}</li>@endforeach @foreach($a->jobCard->parts as $p)<li>{{ $p->description }} × {{ qty($p->qty) }}</li>@endforeach</ul>
        @if($a->status === 'pending')
            <form method="post" action="{{ route('public.fleet-approval.decide', $a->token) }}" class="row g-2">@csrf
                <div class="col-md-6"><input name="name" class="form-control" placeholder="{{ __('Your name') }}" value="{{ $a->fleet->approver_name }}" required></div>
                <div class="col-md-6"><input name="notes" class="form-control" placeholder="{{ __('Notes') }}"></div>
                <div class="col-6"><button name="decision" value="approve" class="btn btn-success w-100">{{ __('Approve') }}</button></div>
                <div class="col-6"><button name="decision" value="reject" class="btn btn-danger w-100">{{ __('Reject') }}</button></div>
            </form>
        @else
            <div class="alert alert-info">{{ __('Decision') }}: {!! status_badge($a->status) !!} — {{ $a->approver_name }} · {{ $a->responded_at?->format('Y-m-d H:i') }}</div>
        @endif
    </div>
</div>
@endsection
