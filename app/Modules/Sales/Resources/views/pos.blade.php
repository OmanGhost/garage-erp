@extends('layouts.app')
@section('title', __('Point of Sale'))
@section('content')
@if(! $session)
    <x-page-header :title="__('Point of Sale')" icon="bi-upc-scan" :breadcrumbs="[__('Point of Sale')]"/>
    <div class="card mx-auto" style="max-width:520px">
        <div class="card-header"><i class="bi bi-cash-stack me-1"></i>{{ __('Open cash session') }}</div>
        <form method="post" action="{{ route('pos.open') }}" class="card-body">@csrf <x-idem/>
            <label class="form-label">{{ __('Cash register') }}</label>
            <select name="cash_register_id" class="form-select mb-3" required>@foreach($registers as $r)<option value="{{ $r->id }}">{{ $r->name }} — {{ $r->branch?->displayName() }}</option>@endforeach</select>
            <label class="form-label">{{ __('Opening cash (float)') }}</label>
            <input type="number" name="opening_cash" step="0.001" min="0" value="0" class="form-control mb-3 font-monospace" required>
            <button class="btn btn-primary w-100" @cannot('pos.open_session') disabled @endcannot><i class="bi bi-unlock"></i> {{ __('Open session') }}</button>
        </form>
    </div>
@else
    <x-page-header :title="__('Point of Sale')" icon="bi-upc-scan" :breadcrumbs="[__('Point of Sale')]"
        :subtitle="__('Session').' '.$session->number.' · '.$session->register->name.' · '.__('opened').' '.$session->opened_at->format('H:i')">
        <a href="{{ route('pos.sessions.show', $session) }}" class="btn btn-outline-secondary"><i class="bi bi-receipt"></i> {{ __('Session report') }}</a>
        @can('pos.close_session')<button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#closeModal"><i class="bi bi-lock"></i> {{ __('Close session') }}</button>@endcan
    </x-page-header>
    <livewire:pos-terminal :session="$session" />
    <div class="modal fade" id="closeModal" tabindex="-1"><div class="modal-dialog"><form method="post" action="{{ route('pos.close') }}" class="modal-content">@csrf <x-idem/>
        <input type="hidden" name="session_id" value="{{ $session->id }}">
        <div class="modal-header"><h5 class="modal-title">{{ __('Close cash session') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="small text-body-secondary">{{ __('Count the cash in the drawer. Any shortage or overage is recorded automatically in the accounts.') }}</p>
            <label class="form-label">{{ __('Counted cash') }}</label><input type="number" name="counted_cash" step="0.001" min="0" class="form-control font-monospace mb-2" required>
            <label class="form-label">{{ __('Notes') }}</label><input name="notes" class="form-control">
        </div>
        <div class="modal-footer"><button class="btn btn-danger">{{ __('Close session') }}</button></div>
    </form></div></div>
@endif
@endsection
