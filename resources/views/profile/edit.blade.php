@extends($layout)
@section('title', __('My profile & security'))
@section('content')
@if($layout === 'layouts.app')<x-page-header :title="__('My profile & security')" icon="bi-person-circle" :breadcrumbs="[__('Profile')]"/>@else<h4 class="mb-3">{{ __('My profile & security') }}</h4>@endif
<div class="row g-3">
    <div class="col-lg-6">
        <form method="post" action="{{ route('profile.update') }}" class="card mb-3">@csrf @method('put')
            <div class="card-header">{{ __('Profile') }}</div>
            <div class="card-body row g-2">
                <div class="col-md-6"><label class="form-label">{{ __('Name') }}</label><input name="name" value="{{ $user->name }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">{{ __('Phone') }}</label><input name="phone" value="{{ $user->phone }}" class="form-control" dir="ltr"></div>
                <div class="col-md-6"><label class="form-label">{{ __('Language') }}</label><select name="locale" class="form-select"><option value="ar" @selected($user->locale === 'ar')>العربية</option><option value="en" @selected($user->locale === 'en')>English</option></select></div>
                <div class="col-md-6"><label class="form-label">{{ __('Theme') }}</label><select name="theme" class="form-select"><option value="light" @selected($user->theme === 'light')>{{ __('Light') }}</option><option value="dark" @selected($user->theme === 'dark')>{{ __('Dark') }}</option></select></div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Save') }}</button></div>
        </form>
        <form method="post" action="{{ route('profile.password') }}" class="card mb-3">@csrf @method('put')
            <div class="card-header">{{ __('Change password') }}</div>
            <div class="card-body row g-2">
                <div class="col-12"><input type="password" name="current_password" class="form-control" placeholder="{{ __('Current password') }}" required autocomplete="current-password"></div>
                <div class="col-md-6"><input type="password" name="password" class="form-control" placeholder="{{ __('New password') }}" required autocomplete="new-password"></div>
                <div class="col-md-6"><input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('Confirm password') }}" required></div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Change password') }}</button></div>
        </form>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header"><i class="bi bi-shield-check me-1"></i>{{ __('Two-factor authentication (2FA)') }}</div><div class="card-body">
            @if($user->hasTwoFactor())
                <div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> {{ __('Enabled since') }} {{ $user->two_factor_confirmed_at->format('Y-m-d') }}</div>
                @if(session('recovery_codes'))<div class="alert alert-warning small"><b>{{ __('Save these recovery codes:') }}</b><div class="font-monospace">{!! implode('<br>', session('recovery_codes')) !!}</div></div>@endif
                <form method="post" action="{{ route('profile.2fa.disable') }}" class="d-flex gap-2">@csrf @method('delete')<input type="password" name="current_password" class="form-control" placeholder="{{ __('Current password') }}" required><button class="btn btn-outline-danger text-nowrap">{{ __('Disable') }}</button></form>
            @elseif($qr)
                <p class="small">{{ __('Scan with Google Authenticator / Microsoft Authenticator, then enter the code.') }}</p>
                <div class="text-center mb-2"><img src="{{ $qr }}" style="width:200px"></div>
                <form method="post" action="{{ route('profile.2fa.confirm') }}" class="d-flex gap-2">@csrf<input name="code" class="form-control font-monospace" placeholder="000000" inputmode="numeric" required><button class="btn btn-primary">{{ __('Confirm') }}</button></form>
            @else
                <p class="small text-body-secondary">{{ __('Protect your account with a one-time code from an authenticator app.') }}</p>
                <form method="post" action="{{ route('profile.2fa.enable') }}">@csrf<button class="btn btn-primary">{{ __('Enable 2FA') }}</button></form>
            @endif
        </div></div>
        <div class="card"><div class="card-header"><i class="bi bi-laptop me-1"></i>{{ __('Active sessions') }}</div>
            <ul class="list-group list-group-flush small">
                @foreach($sessions as $s)<li class="list-group-item">{{ \Illuminate\Support\Str::limit($s->user_agent, 70) }}<div class="text-body-secondary">{{ $s->ip_address }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($s->last_activity)->diffForHumans() }} @if($s->id === session()->getId())<span class="badge text-bg-success">{{ __('This device') }}</span>@endif</div></li>@endforeach
            </ul>
            <form method="post" action="{{ route('profile.sessions.destroy') }}" class="card-body d-flex gap-2">@csrf @method('delete')<input type="password" name="current_password" class="form-control" placeholder="{{ __('Current password') }}" required><button class="btn btn-outline-danger text-nowrap">{{ __('Sign out other sessions') }}</button></form>
        </div>
    </div>
</div>
@endsection
