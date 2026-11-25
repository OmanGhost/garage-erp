@extends('layouts.guest')
@section('title', __('Forgot password'))
@section('content')
<form method="post" action="{{ route('password.email') }}">
    @csrf
    <p class="small text-body-secondary">{{ __('Enter your email and we will send you a password reset link.') }}</p>
    <input type="email" name="email" class="form-control form-control-lg mb-3" required autofocus dir="ltr">
    <button class="btn btn-primary w-100">{{ __('Send reset link') }}</button>
    <div class="text-center mt-2"><a href="{{ route('login') }}" class="small">{{ __('Back to sign in') }}</a></div>
</form>
@endsection
