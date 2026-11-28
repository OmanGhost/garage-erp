{{-- Cash payment popup: amount handed over (notes buttons), change or amount still due; then the change to give back --}}
<div wire:ignore x-data="{
        open: false, step: 'pay', busy: false, total: 0, tendered: '', result: {},
        notes: [['0.100', '{{ __('100 bz') }}'], ['0.500', '{{ __('½ rial') }}'], ['1', '1'], ['5', '5'], ['10', '10'], ['20', '20'], ['50', '50']],
        get t() { return Math.round((parseFloat(this.tendered) || 0) * 1000) / 1000 },
        get diff() { return Math.round((this.t - this.total) * 1000) / 1000 },
        get quick() { const out = []; [1, 5, 10, 20, 50].forEach(s => { const v = Math.ceil(this.total / s) * s; if (v > this.total && ! out.includes(v)) out.push(v) }); return out.slice(0, 4) },
        f(v) { return Number(v || 0).toFixed(3) },
        add(v) { this.tendered = String(Math.round((this.t + parseFloat(v)) * 1000) / 1000); this.$refs.amt.focus() },
        show(e) { this.total = parseFloat(e.detail.total) || 0; this.tendered = ''; this.step = 'pay'; this.busy = false; this.open = true; this.$nextTick(() => this.$refs.amt.focus()) },
        confirm() { if (this.busy || this.t <= 0) return; this.busy = true; $wire.checkoutCash(this.f(this.t)).then(() => { this.busy = false }) },
        done(e) { if (! this.open) return; this.result = e.detail; this.step = 'done' }
    }"
     x-on:pos-cash-open.window="show($event)" x-on:pos-sale-done.window="done($event)" x-on:keydown.escape.window="open && ! busy && (open = false)">
    <template x-if="open">
        <div class="pos-cash-backdrop" x-on:click.self="! busy && (open = false)">
            <div class="pos-cash" role="dialog" aria-modal="true" aria-labelledby="posCashTitle">
                {{-- step 1: amount received --}}
                <div x-show="step === 'pay'">
                    <div class="d-flex align-items-center mb-3">
                        <h2 class="h5 mb-0" id="posCashTitle"><i class="bi bi-cash-stack text-success"></i> {{ __('Cash payment') }}</h2>
                        <button type="button" class="btn-close ms-auto" x-on:click="open = false" :disabled="busy" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="pos-cash-due"><span>{{ __('Amount due') }}</span><b class="font-monospace" x-text="f(total)"></b></div>
                    <label class="form-label fw-semibold mt-3" for="cashAmt">{{ __('Amount received') }}</label>
                    <div class="input-group input-group-lg">
                        <input id="cashAmt" x-ref="amt" type="number" step="0.001" min="0" inputmode="decimal" class="form-control font-monospace text-center fs-3"
                               x-model="tendered" x-on:keydown.enter.prevent="confirm()" placeholder="0.000">
                        <button type="button" class="btn btn-outline-secondary" x-on:click="tendered = ''; $refs.amt.focus()" aria-label="{{ __('Clear') }}"><i class="bi bi-backspace"></i></button>
                    </div>
                    <div class="small text-body-secondary mt-2 mb-1">{{ __('Notes received (each click adds)') }}</div>
                    <div class="pos-cash-notes">
                        <template x-for="n in notes" :key="n[0]"><button type="button" class="btn pos-note" x-on:click="add(n[0])"><span x-text="n[1]"></span></button></template>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" x-on:click="tendered = f(total)">{{ __('Exact amount') }}</button>
                        <template x-for="q in quick" :key="q"><button type="button" class="btn btn-sm btn-outline-secondary font-monospace" x-on:click="tendered = String(q)" x-text="f(q)"></button></template>
                    </div>
                    <div class="pos-cash-result" :class="t <= 0 ? '' : (diff >= 0 ? 'ok' : 'short')">
                        <template x-if="t > 0 && diff >= 0"><div><span>{{ __('Change to give back') }}</span><b class="font-monospace" x-text="f(diff)"></b></div></template>
                        <template x-if="t > 0 && diff < 0"><div><span>{{ __('Still due from the customer') }}</span><b class="font-monospace" x-text="f(-diff)"></b></div></template>
                        <template x-if="t <= 0"><div><span>{{ __('Enter the amount received') }}</span><b>—</b></div></template>
                    </div>
                    <div class="small text-warning-emphasis mt-1" x-show="t > 0 && diff < 0">{{ __('The rest is recorded on the customer account (not allowed for the walk-in customer).') }}</div>
                    <button type="button" class="btn btn-success btn-lg w-100 mt-3" x-on:click="confirm()" :disabled="busy || t <= 0">
                        <span x-show="! busy"><i class="bi bi-check2-circle"></i> {{ __('Confirm & print') }}</span>
                        <span x-show="busy"><span class="spinner-border spinner-border-sm"></span></span>
                    </button>
                </div>

                {{-- step 2: give the change --}}
                <div x-show="step === 'done'" class="text-center">
                    <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
                    <h2 class="h5 mt-2">{{ __('Sale completed') }} <span class="text-body-secondary font-monospace" x-text="result.number"></span></h2>
                    <div class="pos-cash-change">
                        <small>{{ __('Change to give back') }}</small>
                        <b class="font-monospace" x-text="f(result.change)"></b>
                    </div>
                    <div class="d-flex justify-content-center gap-4 small text-body-secondary mb-2">
                        <span>{{ __('Total') }}: <b class="font-monospace" x-text="f(result.total)"></b></span>
                        <span>{{ __('Received') }}: <b class="font-monospace" x-text="f(result.tendered)"></b></span>
                    </div>
                    <div class="alert alert-warning py-2 small" x-show="parseFloat(result.balance) > 0">{{ __('Still due on the customer account') }}: <b class="font-monospace" x-text="f(result.balance)"></b></div>
                    <button type="button" class="btn btn-primary btn-lg w-100" x-on:click="open = false" x-init="$watch('step', s => s === 'done' && $nextTick(() => $el.focus()))"><i class="bi bi-cart-plus"></i> {{ __('New sale') }}</button>
                </div>
            </div>
        </div>
    </template>
</div>
