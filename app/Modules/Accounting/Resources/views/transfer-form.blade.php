@extends('layouts.app')
@section('title', __('Fund transfer'))
@section('content')
@php
    $typeLabels = ['cash' => __('Cash boxes'), 'bank' => __('Bank accounts'), 'card' => __('Card terminals')];
    $state = [
        'accounts' => $accounts,
        'from' => (string) old('from_bank_account_id', $t?->from_bank_account_id ?? ''),
        'to' => (string) old('to_bank_account_id', $t?->to_bank_account_id ?? ''),
        'amount' => (string) old('amount', $t ? (float) $t->amount : ''),
    ];
@endphp
<x-page-header :title="$t ? __('Edit transfer').' '.$t->number : __('Transfer funds between accounts')" icon="bi-arrow-left-right"
    :breadcrumbs="[__('Fund Transfers') => route('crud.index', 'fund-transfers'), $t ? $t->number : __('Add new')]"/>

<form method="post" action="{{ $t ? route('accounting.transfers.update', $t) : route('accounting.transfers.store') }}"
      x-data="fundTransfer(@js($state))" @submit="if (!canSave()) { $event.preventDefault(); }">
    @csrf <x-idem/>
    @if($t) @method('PUT') @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-stretch">
                {{-- FROM --}}
                <div class="col-lg">
                    <div class="xfer-card h-100" :class="{ 'is-error': insufficient }">
                        <div class="xfer-card-head"><span class="xfer-step">1</span> {{ __('From account') }} <span class="text-danger">*</span></div>
                        <select name="from_bank_account_id" class="form-select form-select-lg" x-model="from" required>
                            <option value="">{{ __('Select an account...') }}</option>
                            @foreach(collect($accounts)->groupBy('type') as $type => $list)
                                <optgroup label="{{ $typeLabels[$type] ?? $type }}">
                                    @foreach($list as $a)<option value="{{ $a['id'] }}">{{ $a['name'] }}</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <template x-if="fromAcc">
                            <div class="xfer-balance">
                                <div class="xfer-meta"><span class="badge text-bg-light border" x-text="typeLabel(fromAcc.type)"></span>
                                    <span x-show="fromAcc.bank" x-text="fromAcc.bank"></span> <span x-show="fromAcc.number" dir="ltr" x-text="fromAcc.number"></span></div>
                                <div class="xfer-row"><span>{{ __('Available balance') }}</span><b class="xfer-amount" :class="fromAcc.balance < 0 ? 'text-danger' : ''" x-text="fmt(fromAcc.balance)"></b></div>
                                <div class="xfer-row after"><span>{{ __('Balance after transfer') }}</span>
                                    <b :class="fromAfter < 0 ? 'text-danger' : 'text-body'" x-text="fmt(fromAfter)"></b></div>
                                <div class="xfer-bar"><i :style="`width:${barPercent}%`" :class="insufficient ? 'bg-danger' : 'bg-primary'"></i></div>
                            </div>
                        </template>
                        <div x-show="!fromAcc" class="xfer-empty"><i class="bi bi-wallet2"></i> {{ __('Choose the account the money leaves') }}</div>
                    </div>
                </div>

                {{-- arrow + swap --}}
                <div class="col-lg-auto d-flex flex-lg-column align-items-center justify-content-center gap-2">
                    <span class="xfer-arrow"><i class="bi {{ is_rtl() ? 'bi-arrow-left' : 'bi-arrow-right' }}"></i></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="swap()" :disabled="!from && !to" title="{{ __('Swap accounts') }}">
                        <i class="bi bi-arrow-left-right"></i> <span class="d-lg-none">{{ __('Swap accounts') }}</span>
                    </button>
                </div>

                {{-- TO --}}
                <div class="col-lg">
                    <div class="xfer-card h-100" :class="{ 'is-error': same }">
                        <div class="xfer-card-head"><span class="xfer-step">2</span> {{ __('To account') }} <span class="text-danger">*</span></div>
                        <select name="to_bank_account_id" class="form-select form-select-lg" x-model="to" required>
                            <option value="">{{ __('Select an account...') }}</option>
                            @foreach(collect($accounts)->groupBy('type') as $type => $list)
                                <optgroup label="{{ $typeLabels[$type] ?? $type }}">
                                    @foreach($list as $a)<option value="{{ $a['id'] }}" :disabled="from === '{{ $a['id'] }}'">{{ $a['name'] }}</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <template x-if="toAcc">
                            <div class="xfer-balance">
                                <div class="xfer-meta"><span class="badge text-bg-light border" x-text="typeLabel(toAcc.type)"></span>
                                    <span x-show="toAcc.bank" x-text="toAcc.bank"></span> <span x-show="toAcc.number" dir="ltr" x-text="toAcc.number"></span></div>
                                <div class="xfer-row"><span>{{ __('Current balance') }}</span><b class="xfer-amount" :class="toAcc.balance < 0 ? 'text-danger' : ''" x-text="fmt(toAcc.balance)"></b></div>
                                <div class="xfer-row after"><span>{{ __('Balance after transfer') }}</span><b class="text-success" x-text="fmt(toAfter)"></b></div>
                            </div>
                        </template>
                        <div x-show="!toAcc" class="xfer-empty"><i class="bi bi-bank"></i> {{ __('Choose the account that receives the money') }}</div>
                    </div>
                </div>
            </div>

            {{-- amount --}}
            <div class="xfer-amount-box mt-3">
                <label class="form-label fw-semibold" for="amount"><span class="xfer-step">3</span> {{ __('Amount') }} <span class="text-danger">*</span></label>
                <div class="input-group input-group-lg">
                    <input type="number" id="amount" name="amount" class="form-control text-center fw-bold font-monospace" step="0.001" min="0.001" x-model="amount"
                           :class="{ 'is-invalid': insufficient }" placeholder="0.000" required inputmode="decimal">
                    <span class="input-group-text">{{ tenant()->company()?->currency_code ?? 'OMR' }}</span>
                </div>
                <div x-show="fromAcc && fromAcc.balance > 0" x-cloak><div class="d-flex flex-wrap gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-light border" @click="amount = round(fromAcc.balance)">{{ __('Full balance') }}</button>
                    <button type="button" class="btn btn-sm btn-light border" @click="amount = round(fromAcc.balance / 2)">50%</button>
                    <button type="button" class="btn btn-sm btn-light border" @click="amount = round(fromAcc.balance / 4)">25%</button>
                </div></div>
                <div class="text-danger small mt-1" x-show="insufficient" x-cloak>
                    <i class="bi bi-exclamation-triangle"></i> {{ __('The amount is more than the available balance') }} (<bdi dir="ltr" x-text="fmt(fromAcc?.balance)"></bdi>)
                </div>
                <div class="text-danger small mt-1" x-show="same" x-cloak><i class="bi bi-exclamation-triangle"></i> {{ __('Source and destination accounts must differ.') }}</div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-4"><label class="form-label required">{{ __('Date') }}</label>
                    <input type="date" name="date" class="form-control" value="{{ old('date', $t?->date?->toDateString() ?? now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
                <div class="col-md-4"><label class="form-label">{{ __('Reference') }}</label>
                    <input name="reference" class="form-control" value="{{ old('reference', $t?->reference) }}" maxlength="100" placeholder="{{ __('Deposit slip / transfer no.') }}"></div>
                <div class="col-md-4"><label class="form-label">{{ __('Notes') }}</label>
                    <input name="notes" class="form-control" value="{{ old('notes', $t?->notes) }}" maxlength="255"></div>
            </div>
        </div>

        {{-- summary + actions --}}
        <div class="card-footer d-flex flex-wrap align-items-center gap-2">
            <div class="xfer-summary small" x-show="fromAcc && toAcc && amountNum > 0 && !same">
                <i class="bi bi-info-circle text-primary"></i>
                <span x-text="fmt(amountNum)"></span> {{ __('will move from') }} <b x-text="fromAcc?.name"></b> {{ __('to') }} <b x-text="toAcc?.name"></b>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('crud.index', 'fund-transfers') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                <button class="btn btn-outline-primary" :disabled="!canSave()"><i class="bi bi-save"></i> {{ __('Save draft') }}</button>
                @can('fund_transfers.approve')
                    <button class="btn btn-primary" name="post_now" value="1" :disabled="!canSave()" data-confirm="{{ __('Post this transfer?') }}"><i class="bi bi-check2-circle"></i> {{ __('Save & post') }}</button>
                @endcan
            </div>
        </div>
    </div>
</form>

@push('styles')
<style>
    [x-cloak] { display: none !important; }
    .xfer-card { border: 1px solid var(--border-soft); border-radius: .9rem; padding: 1rem; background: var(--surface); transition: border-color .15s, box-shadow .15s; }
    .xfer-card:focus-within { border-color: var(--brand); box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--brand) 15%, transparent); }
    .xfer-card.is-error { border-color: var(--bs-danger); }
    .xfer-card-head { font-weight: 700; margin-bottom: .6rem; display: flex; align-items: center; gap: .5rem; }
    .xfer-step { display: inline-flex; width: 24px; height: 24px; border-radius: 50%; background: var(--brand); color: #fff; font-size: .8rem; align-items: center; justify-content: center; }
    .xfer-balance { margin-top: .85rem; }
    .xfer-meta { font-size: .8rem; color: var(--bs-secondary-color); display: flex; gap: .4rem; flex-wrap: wrap; align-items: center; margin-bottom: .5rem; }
    .xfer-row { display: flex; justify-content: space-between; align-items: baseline; padding: .25rem 0; }
    .xfer-row.after { border-top: 1px dashed var(--border-soft); margin-top: .25rem; padding-top: .45rem; font-size: .92rem; }
    .xfer-amount { font-size: 1.6rem; font-variant-numeric: tabular-nums; }
    .xfer-row b { font-variant-numeric: tabular-nums; direction: ltr; }
    .xfer-bar { height: 6px; background: var(--bs-tertiary-bg); border-radius: 6px; overflow: hidden; margin-top: .5rem; }
    .xfer-bar i { display: block; height: 100%; border-radius: 6px; transition: width .2s; }
    .xfer-empty { margin-top: 1rem; color: var(--bs-secondary-color); text-align: center; padding: 1.25rem .5rem; border: 1px dashed var(--border-soft); border-radius: .6rem; }
    .xfer-arrow { width: 46px; height: 46px; border-radius: 50%; background: color-mix(in srgb, var(--brand) 12%, transparent); color: var(--brand); display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; }
    @media (max-width: 991.98px) { .xfer-arrow i { transform: rotate(-90deg); } }
    .xfer-amount-box { max-width: 460px; margin-inline: auto; text-align: center; }
    .xfer-amount-box .input-group input { font-size: 1.5rem; }
    .xfer-amount-box .d-flex { justify-content: center; }
</style>
@endpush

@push('scripts')
<script>
    function fundTransfer(init) {
        const decimals = (window.ERP && ERP.decimals) || 3;
        const types = @js($typeLabels);
        return {
            accounts: init.accounts.map((a) => ({ ...a, balance: parseFloat(a.balance) })),
            from: init.from, to: init.to, amount: init.amount,
            get fromAcc() { return this.accounts.find((a) => String(a.id) === String(this.from)) || null; },
            get toAcc() { return this.accounts.find((a) => String(a.id) === String(this.to)) || null; },
            get amountNum() { return parseFloat(this.amount) || 0; },
            get fromAfter() { return this.fromAcc ? this.round(this.fromAcc.balance - this.amountNum) : 0; },
            get toAfter() { return this.toAcc ? this.round(this.toAcc.balance + this.amountNum) : 0; },
            get same() { return this.from !== '' && this.from === this.to; },
            get insufficient() { return !!this.fromAcc && this.amountNum > 0 && this.amountNum - this.fromAcc.balance > 0.0005; },
            get barPercent() { return this.fromAcc && this.fromAcc.balance > 0 ? Math.min(100, (this.amountNum / this.fromAcc.balance) * 100) : (this.amountNum > 0 ? 100 : 0); },
            canSave() { return this.fromAcc && this.toAcc && !this.same && this.amountNum > 0 && !this.insufficient; },
            swap() { [this.from, this.to] = [this.to, this.from]; },
            round(v) { const f = Math.pow(10, decimals); return Math.round((v + Number.EPSILON) * f) / f; },
            fmt(v) { return (parseFloat(v) || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }); },
            typeLabel(t) { return types[t] || t; },
        };
    }
</script>
@endpush
@endsection
