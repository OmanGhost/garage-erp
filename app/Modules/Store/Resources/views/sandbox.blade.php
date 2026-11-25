@extends('layouts.store')
@section('title', __('Test payment'))
@section('content')
<div class="card mx-auto" style="max-width:420px"><div class="card-body text-center">
    <span class="badge text-bg-warning mb-2">SANDBOX</span>
    <h5>{{ __('Test payment gateway') }}</h5>
    <p>{{ $p->reference }}<br><b class="fs-4">{{ money($p->amount) }}</b></p>
    <form method="post" action="{{ route('payments.sandbox.complete', $p->session_id) }}" class="d-grid gap-2">@csrf
        <button name="result" value="paid" class="btn btn-success">{{ __('Simulate successful payment') }}</button>
        <button name="result" value="failed" class="btn btn-outline-danger">{{ __('Simulate failure') }}</button>
    </form>
</div></div>
@endsection
