{{-- SMTP settings form (company settings and platform admin). $c = saved credentials, $action / $testAction = routes --}}
@php $c = $c ?? []; @endphp
<form method="post" action="{{ $action }}" class="row g-2" autocomplete="off">@csrf
    <div class="col-md-6"><label class="form-label small mb-0" for="smtp_host">{{ __('SMTP server') }}</label>
        <input id="smtp_host" name="host" class="form-control @error('host') is-invalid @enderror" dir="ltr" placeholder="mail.yourdomain.om" value="{{ old('host', $c['host'] ?? '') }}" required>
        @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label class="form-label small mb-0" for="smtp_port">{{ __('Port') }}</label>
        <input id="smtp_port" name="port" type="number" class="form-control @error('port') is-invalid @enderror" dir="ltr" value="{{ old('port', $c['port'] ?? 465) }}" required></div>
    <div class="col-md-3"><label class="form-label small mb-0" for="smtp_enc">{{ __('Encryption') }}</label>
        <select id="smtp_enc" name="encryption" class="form-select">@foreach(['ssl' => 'SSL (465)', 'tls' => 'TLS / STARTTLS (587)', 'none' => __('None')] as $k => $l)<option value="{{ $k }}" @selected(old('encryption', $c['encryption'] ?? 'ssl') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label small mb-0" for="smtp_user">{{ __('Username') }}</label>
        <input id="smtp_user" name="username" class="form-control" dir="ltr" value="{{ old('username', $c['username'] ?? '') }}" autocomplete="off"></div>
    <div class="col-md-6"><label class="form-label small mb-0" for="smtp_pass">{{ __('Password') }}</label>
        <input id="smtp_pass" name="password" type="password" class="form-control" dir="ltr" autocomplete="new-password" placeholder="{{ ! empty($c['password']) ? __('Saved — leave empty to keep') : '' }}"></div>
    <div class="col-md-6"><label class="form-label small mb-0" for="smtp_from">{{ __('From address') }}</label>
        <input id="smtp_from" name="from_address" type="email" class="form-control @error('from_address') is-invalid @enderror" dir="ltr" placeholder="no-reply@yourdomain.om" value="{{ old('from_address', $c['from_address'] ?? '') }}" required>
        @error('from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-6"><label class="form-label small mb-0" for="smtp_name">{{ __('From name') }}</label>
        <input id="smtp_name" name="from_name" class="form-control" value="{{ old('from_name', $c['from_name'] ?? '') }}"></div>
    @if($withActive ?? true)
        <div class="col-12"><div class="form-check form-switch"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="smtp_active" @checked(old('is_active', $active ?? true))><label class="form-check-label" for="smtp_active">{{ __('Send our e-mails through this server') }}</label></div></div>
    @endif
    <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
        <button class="btn btn-primary"><i class="bi bi-check2"></i> {{ __('Save e-mail settings') }}</button>
        <span class="small text-body-secondary">{{ __('cPanel: use the mail server of your domain (mail.yourdomain), port 465 SSL, and the full e-mail address as username.') }}</span>
    </div>
</form>
@if(! empty($c['host']))
<form method="post" action="{{ $testAction }}" class="d-flex gap-2 mt-3 border-top pt-3">@csrf
    <input name="test_to" type="email" class="form-control" style="max-width:320px" dir="ltr" placeholder="{{ __('Send a test to...') }}" value="{{ auth()->user()->email }}" required aria-label="{{ __('Send a test to...') }}">
    <button class="btn btn-outline-secondary text-nowrap"><i class="bi bi-send"></i> {{ __('Send test e-mail') }}</button>
</form>
@endif
