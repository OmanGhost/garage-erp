@extends('layouts.guest')
@section('title', __('Two-factor authentication'))
@section('content')
<p class="text-body-secondary small text-center">{{ __('Enter the 6-digit code from your authenticator app.') }}</p>
<form method="post" action="{{ url('/two-factor') }}" x-data="{ recovery: false }">
    @csrf
    <div class="mb-3" id="codeBox"><input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="form-control form-control-lg text-center font-monospace" placeholder="000000" autofocus></div>
    <details class="mb-3 small"><summary>{{ __('Use a recovery code') }}</summary><input name="recovery_code" class="form-control mt-2 font-monospace" dir="ltr"></details>
    <button class="btn btn-primary btn-lg w-100">{{ __('Verify') }}</button>
</form>
@endsection
