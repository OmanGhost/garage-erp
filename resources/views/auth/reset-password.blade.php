@extends('layouts.guest')
@section('title', __('Reset password'))
@section('content')
<form method="post" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="mb-2"><label class="form-label">{{ __('Email') }}</label><input type="email" name="email" value="{{ old('email', $email) }}" class="form-control" required dir="ltr"></div>
    <div class="mb-2"><label class="form-label">{{ __('New password') }}</label><input type="password" name="password" class="form-control" required autocomplete="new-password"></div>
    <div class="mb-3"><label class="form-label">{{ __('Confirm password') }}</label><input type="password" name="password_confirmation" class="form-control" required></div>
    <div class="form-text mb-2">{{ __('At least 10 characters with upper and lower case letters and numbers.') }}</div>
    <button class="btn btn-primary w-100">{{ __('Reset password') }}</button>
</form>
@endsection
