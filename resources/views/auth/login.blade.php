@extends('layouts.guest')
@section('title', __('Sign in'))
@section('content')
<form method="post" action="{{ url('/login') }}">
    @csrf
    <div class="mb-3"><label class="form-label">{{ __('Email') }}</label>
        <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg @error('email') is-invalid @enderror" required autofocus autocomplete="username" dir="ltr"></div>
    <div class="mb-3"><label class="form-label">{{ __('Password') }}</label>
        <input type="password" name="password" class="form-control form-control-lg" required autocomplete="current-password" dir="ltr"></div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="remember" id="rem"><label class="form-check-label" for="rem">{{ __('Remember me') }}</label></div>
        <a href="{{ route('password.request') }}" class="small">{{ __('Forgot password?') }}</a>
    </div>
    <button class="btn btn-primary btn-lg w-100"><i class="bi bi-box-arrow-in-right"></i> {{ __('Sign in') }}</button>
</form>
@endsection
