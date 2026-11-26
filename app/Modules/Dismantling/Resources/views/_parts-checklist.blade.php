{{--
    Standard parts checklist: tick the parts taken from the vehicle; the factory part number (OEM) for this make / model
    is filled in from earlier vehicles and from the catalog. Used in the "new dismantling vehicle" form ($vehicle = null:
    make / model / year are read from the form) and on the dismantling file ($vehicle given).
--}}
@php
    $groups = \App\Modules\Dismantling\Services\DismantlingService::checklist();
    $vehicle = $vehicle ?? null;
    $canStock = auth()->user()->can('dismantling.approve');
@endphp
<div class="col-12 mb-3" x-data="dismantleChecklist({
        url: @js(route('dismantling.suggest')),
        fixed: @js($vehicle ? ['make' => $vehicle->vehicle_make_id, 'model' => $vehicle->vehicle_model_id, 'year' => $vehicle->year] : null),
        old: @js(old('checklist', [])),
    })">
    @unless($vehicle)<div class="form-section-title">{{ __('Parts to extract') }}</div>@endunless
    <div class="checklist-bar">
        <div class="small text-body-secondary flex-grow-1">
            <i class="bi bi-info-circle"></i> {{ __('Tick the parts taken from this vehicle. Factory part numbers (OEM) for the same make and model are filled in automatically from earlier vehicles and the catalog.') }}
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge text-bg-primary fs-6"><span x-text="count()"></span> {{ __('parts') }}</span>
            <span class="badge text-bg-light fs-6 font-monospace" x-text="fmt(total())"></span>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="load(true)" :disabled="loading">
                <span x-show="loading" class="spinner-border spinner-border-sm"></span><i x-show="!loading" class="bi bi-arrow-repeat"></i> {{ __('Fill part numbers') }}
            </button>
        </div>
    </div>
    <div class="small mb-2" x-show="message" x-text="message" :class="found ? 'text-success' : 'text-body-secondary'"></div>

    <div class="row g-2">
        @foreach($groups as $g)
            <div class="col-lg-6">
                <div class="card checklist-group h-100">
                    <div class="card-header d-flex align-items-center gap-2 py-2">
                        <i class="bi {{ $g['icon'] }} text-brand"></i><span class="fw-semibold">{{ $g['label'] }}</span>
                        <span class="badge rounded-pill text-bg-secondary" x-text="groupCount(@js(array_keys($g['parts'])))"></span>
                        <button type="button" class="btn btn-sm btn-link ms-auto p-0" @click="toggleGroup(@js(array_keys($g['parts'])))">{{ __('Select all') }}</button>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach($g['parts'] as $p)
                            <div class="list-group-item checklist-row" :class="rows[@js($p['key'])].selected && 'is-on'">
                                <label class="d-flex align-items-center gap-2 mb-0" style="cursor:pointer">
                                    <input type="checkbox" class="form-check-input mt-0" x-model="rows[@js($p['key'])].selected">
                                    <span class="flex-grow-1">{{ $p['name'] }} <span class="small text-body-secondary">· {{ $p['alt'] }}</span></span>
                                    <template x-if="rows[@js($p['key'])].src"><span class="badge" :class="rows[@js($p['key'])].src === 'history' ? 'text-bg-success-subtle text-success-emphasis' : 'text-bg-info-subtle text-info-emphasis'" :title="rows[@js($p['key'])].srcName || ''"><i class="bi bi-upc"></i> <bdi dir="ltr" x-text="rows[@js($p['key'])].suggested"></bdi></span></template>
                                </label>
                                <template x-if="rows[@js($p['key'])].selected">
                                    <div class="checklist-inputs">
                                        <input type="hidden" name="checklist[{{ $p['key'] }}][selected]" value="1">
                                        <input class="form-control form-control-sm font-monospace" dir="ltr" name="checklist[{{ $p['key'] }}][oem]" x-model="rows[@js($p['key'])].oem" placeholder="{{ __('OEM no.') }}" aria-label="{{ __('OEM no.') }}" maxlength="80">
                                        <select class="form-select form-select-sm" name="checklist[{{ $p['key'] }}][grade]" x-model="rows[@js($p['key'])].grade" aria-label="{{ __('Grade') }}">
                                            <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="damaged">{{ __('Damaged') }}</option>
                                        </select>
                                        <input type="number" step="0.001" min="0" class="form-control form-control-sm" name="checklist[{{ $p['key'] }}][estimated_value]" x-model="rows[@js($p['key'])].estimated_value" placeholder="{{ __('Est. value') }}" aria-label="{{ __('Est. value') }}">
                                        <input type="number" step="0.001" min="0" class="form-control form-control-sm" name="checklist[{{ $p['key'] }}][sale_price]" x-model="rows[@js($p['key'])].sale_price" placeholder="{{ __('Sale price') }}" aria-label="{{ __('Sale price') }}">
                                    </div>
                                </template>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @if($canStock)
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" role="switch" id="checklist_to_stock" name="checklist_to_stock" value="1" @checked(old('checklist_to_stock'))>
            <label class="form-check-label" for="checklist_to_stock">{{ __('Move the selected parts to the warehouse now (cost is allocated by estimated value)') }}</label>
        </div>
    @endif
    @error('checklist')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
@once
<script>
    window.dismantleChecklist = function (cfg) {
        const keys = @js(collect($groups)->flatMap(fn ($g) => array_keys($g['parts']))->values());
        const rows = {};
        keys.forEach(k => { const o = (cfg.old || {})[k] || {}; rows[k] = { selected: !!o.selected, oem: o.oem || '', grade: o.grade || 'A', estimated_value: o.estimated_value || '', sale_price: o.sale_price || '', src: '', suggested: '', srcName: '' }; });
        return {
            rows, loading: false, message: '', found: false,
            init() {
                if (cfg.fixed) { this.load(false); return; }
                const form = this.$el.closest('form');
                ['vehicle_make_id', 'vehicle_model_id', 'year'].forEach(n => form?.querySelector(`[name="${n}"]`)?.addEventListener('change', () => this.load(false)));
                this.load(false);
            },
            params() {
                if (cfg.fixed) return cfg.fixed;
                const form = this.$el.closest('form'), v = n => form?.querySelector(`[name="${n}"]`)?.value || '';
                return { make: v('vehicle_make_id'), model: v('vehicle_model_id'), year: v('year') };
            },
            load(manual) {
                const p = this.params();
                if (!p.make) { if (manual) { this.message = @js(__('Choose the make (and model / year) first.')); this.found = false; } return; }
                this.loading = true;
                fetch(cfg.url + '?' + new URLSearchParams({ make: p.make || '', model: p.model || '', year: p.year || '' }), { headers: { Accept: 'application/json' } })
                    .then(r => r.json()).then(j => {
                        const s = j.suggestions || {}; let n = 0;
                        Object.keys(this.rows).forEach(k => {
                            const r = this.rows[k], x = s[k];
                            r.src = x ? x.source : ''; r.suggested = x ? x.oem : ''; r.srcName = x && x.name ? x.name : '';
                            if (x) { n++; if (!r.oem) r.oem = x.oem; if (!r.sale_price && x.sale_price && Number(x.sale_price) > 0) r.sale_price = x.sale_price; }
                        });
                        this.found = n > 0;
                        this.message = n ? @js(__(':n factory part numbers found for this vehicle.')).replace(':n', n) : (manual ? @js(__('No saved part numbers for this vehicle yet — the ones you enter now will be suggested next time.')) : '');
                    }).finally(() => { this.loading = false; });
            },
            count() { return Object.values(this.rows).filter(r => r.selected).length; },
            groupCount(ks) { return ks.filter(k => this.rows[k].selected).length; },
            toggleGroup(ks) { const on = !ks.every(k => this.rows[k].selected); ks.forEach(k => this.rows[k].selected = on); },
            total() { return Object.values(this.rows).filter(r => r.selected).reduce((s, r) => s + (Number(r.estimated_value) || 0), 0); },
            fmt(v) { return (Math.round(v * 1000) / 1000).toFixed(3); },
        };
    };
</script>
@endonce
