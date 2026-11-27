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
    <div class="modal fade" id="closeModal" tabindex="-1" aria-labelledby="closeModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form method="post" action="{{ route('pos.close') }}" class="modal-content" x-data="{ counted: '', expected: {{ (float) $summary['expected'] }},
            init() { this.$el.closest('.modal').addEventListener('show.bs.modal', () => this.refresh()) },
            refresh() { fetch(@js(route('pos.sessions.summary', $session)), { headers: { 'Accept': 'text/html' } }).then(r => r.ok ? r.text() : null).then(h => {
                if (! h) return; this.$refs.sum.innerHTML = h; const x = this.$refs.sum.querySelector('[data-expected]'); if (x) this.expected = parseFloat(x.dataset.expected) }) },
            get diff() { return this.counted === '' ? null : Math.round(((parseFloat(this.counted) || 0) - this.expected) * 1000) / 1000 } }">@csrf <x-idem/>
        <input type="hidden" name="session_id" value="{{ $session->id }}">
        <div class="modal-header"><h5 class="modal-title" id="closeModalTitle">{{ __('Close cash session') }} · {{ $session->number }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
        <div class="modal-body">
            <div x-ref="sum">@include('sales::partials.close-summary', ['sm' => $summary])</div>

            {{-- 3. the count --}}
            <label class="form-label fw-semibold" for="countedCash">{{ __('Counted cash') }}</label>
            <input type="number" name="counted_cash" id="countedCash" step="0.001" min="0" class="form-control form-control-lg font-monospace mb-2" required x-model="counted">
            <div class="alert py-2 mb-2" x-show="diff !== null" x-cloak
                 :class="diff === 0 ? 'alert-success' : (diff < 0 ? 'alert-danger' : 'alert-warning')">
                <span x-show="diff === 0"><i class="bi bi-check-circle"></i> {{ __('The drawer matches the expected cash.') }}</span>
                <span x-show="diff < 0"><i class="bi bi-exclamation-triangle"></i> {{ __('Shortage') }}: <b class="font-monospace" x-text="Math.abs(diff).toFixed(3)"></b></span>
                <span x-show="diff > 0"><i class="bi bi-exclamation-circle"></i> {{ __('Overage') }}: <b class="font-monospace" x-text="diff.toFixed(3)"></b></span>
            </div>
            <div x-show="diff !== null && diff !== 0" x-cloak>
                <label class="form-label fw-semibold" for="diffReason">{{ __('Reason for the difference') }} <span class="text-danger">*</span></label>
                <textarea name="difference_reason" id="diffReason" rows="2" maxlength="500" class="form-control mb-1" :required="diff !== null && diff !== 0" placeholder="{{ __('e.g. change given twice, unrecorded expense…') }}">{{ old('difference_reason') }}</textarea>
                <div class="form-text mb-2">{{ __('The shortage or overage is recorded in the accounts with this reason.') }}</div>
            </div>
            <label class="form-label" for="closeNotes">{{ __('Notes') }}</label><input name="notes" id="closeNotes" class="form-control" maxlength="500">
        </div>
        <div class="modal-footer"><button class="btn btn-danger"><i class="bi bi-lock"></i> {{ __('Close session') }}</button></div>
    </form></div></div>
@endif
@endsection
