@extends('layouts.app')
@section('title', __('Vehicle Reception'))
@section('content')
<x-page-header :title="__('Vehicle Reception')" icon="bi-box-arrow-in-down" :breadcrumbs="[__('Workshop') => route('workshop.board'), __('Vehicle Reception')]"
               :subtitle="__('Register the customer and vehicle, record condition, belongings and signature, and open a job card.')"/>

<form method="post" action="{{ route('reception.store') }}" enctype="multipart/form-data" x-data="reception()" @submit="beforeSubmit">
    @csrf <x-idem/>
    @if($appointment)<input type="hidden" name="appointment_id" value="{{ $appointment->id }}">@endif
    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person me-1"></i>1. {{ __('Customer') }}</div>
                <div class="card-body">
                    <div class="btn-group mb-3" role="group">
                        <input type="radio" class="btn-check" id="cx" value="existing" x-model="customerMode"><label class="btn btn-outline-primary btn-sm" for="cx">{{ __('Existing customer') }}</label>
                        <input type="radio" class="btn-check" id="cn" value="new" x-model="customerMode"><label class="btn btn-outline-primary btn-sm" for="cn">{{ __('New customer') }}</label>
                    </div>
                    <div x-show="customerMode === 'existing'">
                        <div @lookup-selected="customerId = $event.detail.id; vehicleMode = 'existing'">
                            <x-lookup name="customer_id" type="customers" :value="$customer?->id" :text="$customer ? $customer->name.' — '.$customer->phone : ''" :placeholder="__('Search by name, mobile or code...')" id="lk_customer_id"/>
                        </div>
                    </div>
                    <div class="row g-2" x-show="customerMode === 'new'" x-cloak>
                        <div class="col-md-6"><label class="form-label required">{{ __('Name') }}</label><input name="customer_name" class="form-control" value="{{ old('customer_name') }}" :disabled="customerMode !== 'new'"></div>
                        <div class="col-md-6"><label class="form-label required">{{ __('Mobile') }}</label><input name="customer_phone" class="form-control" dir="ltr" placeholder="+968 9xxxxxxx" value="{{ old('customer_phone') }}" :disabled="customerMode !== 'new'"></div>
                        <div class="col-md-6"><label class="form-label">{{ __('Email') }}</label><input type="email" name="customer_email" class="form-control" :disabled="customerMode !== 'new'"></div>
                        <div class="col-md-6"><label class="form-label">{{ __('Type') }}</label><select name="customer_type" class="form-select" :disabled="customerMode !== 'new'"><option value="individual">{{ __('Individual') }}</option><option value="company">{{ __('Company') }}</option><option value="government">{{ __('Government') }}</option></select></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-car-front me-1"></i>2. {{ __('Vehicle') }}</div>
                <div class="card-body">
                    <div class="btn-group mb-3">
                        <input type="radio" class="btn-check" id="vx" value="existing" x-model="vehicleMode" :disabled="customerMode === 'new'"><label class="btn btn-outline-primary btn-sm" for="vx">{{ __('Registered vehicle') }}</label>
                        <input type="radio" class="btn-check" id="vn" value="new" x-model="vehicleMode"><label class="btn btn-outline-primary btn-sm" for="vn">{{ __('New vehicle') }}</label>
                    </div>
                    <div x-show="vehicleMode === 'existing' && customerMode === 'existing'">
                        <div @lookup-selected="odometer = $event.detail.extra?.odometer || odometer">
                            <x-lookup name="vehicle_id" type="vehicles" :value="$appointment?->vehicle_id" :text="$appointment?->vehicle?->label() ?? ''" params="customer_id=#lk_customer_id" :placeholder="__('Search plate / VIN...')" id="lk_vehicle_id"/>
                        </div>
                        <div class="form-text">{{ __('Only vehicles of the selected customer are listed.') }}</div>
                    </div>
                    <div class="row g-2" x-show="vehicleMode === 'new' || customerMode === 'new'" x-cloak>
                        <div class="col-md-3"><label class="form-label required">{{ __('Plate number') }}</label><input name="plate_number" class="form-control" dir="ltr" value="{{ old('plate_number') }}"></div>
                        <div class="col-md-2"><label class="form-label">{{ __('Code') }}</label><input name="plate_code" class="form-control text-uppercase" dir="ltr" maxlength="10" value="{{ old('plate_code') }}"></div>
                        <div class="col-md-2"><label class="form-label">{{ __('Country') }}</label><select name="plate_country" class="form-select"><option>OM</option><option>AE</option><option>SA</option><option>QA</option><option>KW</option><option>BH</option></select></div>
                        <div class="col-md-5"><label class="form-label">{{ __('VIN (chassis)') }}</label>
                            <div class="input-group"><input name="vin" class="form-control text-uppercase" dir="ltr" maxlength="17" x-model="vin">
                                <button type="button" class="btn btn-outline-secondary" @click="decodeVin" title="{{ __('Decode VIN') }}"><i class="bi bi-magic"></i></button></div>
                            <div class="form-text" x-text="vinInfo"></div>
                        </div>
                        <div class="col-md-4"><label class="form-label">{{ __('Make') }}</label>
                            <select name="vehicle_make_id" id="f_vehicle_make_id" class="form-select" x-model="makeId"><option value="">—</option>@foreach($makes as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">{{ __('Model') }}</label><x-lookup name="vehicle_model_id" type="vehicle_models" params="make_id=#f_vehicle_make_id"/></div>
                        <div class="col-md-2"><label class="form-label">{{ __('Year') }}</label><input type="number" name="year" class="form-control" min="1950" max="{{ date('Y') + 1 }}" x-model="year"></div>
                        <div class="col-md-2"><label class="form-label">{{ __('Color') }}</label><input name="color" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">{{ __('Vehicle type') }}</label><select name="vehicle_type" class="form-select">@foreach(\App\Support\Options::VEHICLE_TYPES as $t)<option value="{{ $t }}">{{ status_label($t) }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">{{ __('Fuel') }}</label><select name="fuel_type" class="form-select"><option value="">—</option>@foreach(\App\Support\Options::FUEL_TYPES as $t)<option value="{{ $t }}">{{ status_label($t) }}</option>@endforeach</select></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-clipboard-pulse me-1"></i>3. {{ __('Condition at reception') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label required">{{ __('Odometer (km)') }}</label><input type="number" name="odometer" class="form-control font-monospace" min="0" required x-model="odometer"></div>
                        <div class="col-md-8"><label class="form-label required">{{ __('Fuel level') }}: <b x-text="fuel + '%'"></b></label>
                            <input type="range" class="form-range" name="fuel_level" min="0" max="100" step="12.5" x-model="fuel">
                            <div class="d-flex justify-content-between small text-body-secondary"><span>E</span><span>¼</span><span>½</span><span>¾</span><span>F</span></div></div>
                        <div class="col-12"><label class="form-label required">{{ __('Reported problems (customer complaint)') }}</label>
                            <textarea name="reported_issues" class="form-control" rows="3" required>{{ old('reported_issues') }}</textarea></div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Existing damages & scratches') }} <span class="small text-body-secondary">— {{ __('click on the diagram to mark') }}</span></label>
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <div class="damage-map border rounded p-2 bg-white" @click="addDamage($event)">
                                        @include('workshop::partials.car-diagram')
                                        <template x-for="(d, i) in damages" :key="i"><span class="damage-dot" :style="`left:${d.x}%;top:${d.y}%`" x-text="i+1" @click.stop="damages.splice(i,1)"></span></template>
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <template x-for="(d, i) in damages" :key="'r'+i">
                                        <div class="input-group input-group-sm mb-1">
                                            <span class="input-group-text" x-text="i+1"></span>
                                            <select class="form-select" :name="`damages[${i}][type]`" x-model="d.type" style="max-width:120px">
                                                <option value="scratch">{{ __('Scratch') }}</option><option value="dent">{{ __('Dent') }}</option><option value="broken">{{ __('Broken') }}</option><option value="paint">{{ __('Paint') }}</option><option value="rust">{{ __('Rust') }}</option>
                                            </select>
                                            <input class="form-control" :name="`damages[${i}][description]`" x-model="d.description" placeholder="{{ __('Description') }}">
                                            <input type="hidden" :name="`damages[${i}][x]`" :value="d.x"><input type="hidden" :name="`damages[${i}][y]`" :value="d.y"><input type="hidden" :name="`damages[${i}][area]`" :value="d.area">
                                            <button type="button" class="btn btn-outline-danger" @click="damages.splice(i,1)"><i class="bi bi-x"></i></button>
                                        </div>
                                    </template>
                                    <div class="small text-body-secondary" x-show="!damages.length">{{ __('No damages recorded.') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Personal items left in the vehicle') }}</label>
                            <template x-for="(it, i) in items" :key="'i'+i">
                                <div class="input-group input-group-sm mb-1"><input class="form-control" name="personal_items[]" x-model="items[i]"><button type="button" class="btn btn-outline-danger" @click="items.splice(i,1)"><i class="bi bi-x"></i></button></div>
                            </template>
                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="items.push('')"><i class="bi bi-plus"></i> {{ __('Add item') }}</button>
                            <div class="mt-2 d-flex flex-wrap gap-1">@foreach(['Spare tyre', 'Jack', 'Documents', 'Sunglasses', 'Charger', 'Child seat', 'Remote key'] as $s)<button type="button" class="btn btn-sm btn-light" @click="items.push(@js(__($s)))">+ {{ __($s) }}</button>@endforeach</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Quick checklist') }}</label>
                            @foreach(['spare_tyre' => 'Spare tyre present', 'warning_lights' => 'Warning lights on dashboard', 'ac_working' => 'A/C working', 'radio_working' => 'Radio / screen working', 'keys' => 'Keys received'] as $k => $l)
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="checklist[{{ $k }}]" value="1" id="cl_{{ $k }}"><label class="form-check-label" for="cl_{{ $k }}">{{ __($l) }}</label></div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-gear me-1"></i>4. {{ __('Job details') }}</div>
                <div class="card-body row g-2">
                    <div class="col-md-6"><label class="form-label">{{ __('Job type') }}</label><select name="job_type" class="form-select">@foreach(['repair', 'maintenance', 'warranty', 'insurance', 'internal'] as $t)<option value="{{ $t }}">{{ status_label($t) }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Priority') }}</label><select name="priority" class="form-select">@foreach(\App\Support\Options::PRIORITIES as $p)<option value="{{ $p }}" @selected($p === 'normal')>{{ status_label($p) }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Department') }}</label><select name="department_id" class="form-select"><option value="">—</option>@foreach($departments as $id => $n)<option value="{{ $id }}" @selected($appointment?->department_id === $id)>{{ $n }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Service advisor') }}</label><select name="service_advisor_id" class="form-select">@foreach($advisors as $id => $n)<option value="{{ $id }}" @selected($id === auth()->id())>{{ $n }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label">{{ __('Expected delivery') }}</label><input type="datetime-local" name="expected_delivery_at" class="form-control" value="{{ now()->addDay()->setTime(17, 0)->format('Y-m-d\TH:i') }}"></div>
                    <div class="col-12"><label class="form-label">{{ __('Internal notes') }}</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-camera me-1"></i>5. {{ __('Photos (front, back, sides, interior, dashboard)') }}</div>
                <div class="card-body">
                    <input type="file" name="photos[]" class="form-control" accept="image/*" capture="environment" multiple @change="previews = Array.from($event.target.files).map(f => URL.createObjectURL(f))">
                    <div class="d-flex flex-wrap gap-2 mt-2"><template x-for="p in previews"><img :src="p" class="rounded border" style="width:84px;height:64px;object-fit:cover"></template></div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-pen me-1"></i>6. {{ __('Customer signature') }}</div>
                <div class="card-body">
                    <canvas id="sigPad" class="signature-pad"></canvas>
                    <input type="hidden" name="signature" id="sigInput">
                    <div class="d-flex gap-2 mt-2">
                        <input name="customer_signed_name" class="form-control form-control-sm" placeholder="{{ __('Signed by (name)') }}">
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="pad && pad.clear(); document.getElementById('sigInput').value=''">{{ __('Clear') }}</button>
                    </div>
                    <div class="form-text">{{ __('The customer confirms the vehicle condition, belongings and authorizes inspection.') }}</div>
                </div>
            </div>
            <div class="card"><div class="card-body">
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="notify" value="1" id="notify" checked><label class="form-check-label" for="notify">{{ __('Send reception confirmation to the customer (SMS/WhatsApp)') }}</label></div>
                <button class="btn btn-primary btn-lg w-100"><i class="bi bi-check2-circle"></i> {{ __('Receive vehicle & open job card') }}</button>
            </div></div>
        </div>
    </div>
</form>
@endsection
@push('scripts')
<script src="{{ asset('vendor/signature/signature_pad.umd.min.js') }}"></script>
<script>
function reception() {
    return {
        customerMode: @js($customer ? 'existing' : 'existing'), vehicleMode: @js($appointment?->vehicle_id ? 'existing' : 'existing'),
        customerId: @js($customer?->id), odometer: '', fuel: 50, damages: [], items: [], previews: [], vin: '', vinInfo: '', makeId: '', year: '', pad: null,
        init() { this.$nextTick(() => { this.pad = erpSignature('sigPad', 'sigInput'); }); },
        addDamage(e) {
            const r = e.currentTarget.getBoundingClientRect();
            const x = ((e.clientX - r.left) / r.width * 100).toFixed(1), y = ((e.clientY - r.top) / r.height * 100).toFixed(1);
            const area = y < 30 ? 'front' : (y > 70 ? 'rear' : (x < 50 ? 'left' : 'right'));
            this.damages.push({ x, y, area, type: 'scratch', description: '' });
        },
        decodeVin() {
            if (this.vin.length !== 17) { this.vinInfo = @js(__('VIN must be 17 characters.')); return; }
            this.vinInfo = '...';
            fetch(@js(route('vehicles.vin')) + '?vin=' + encodeURIComponent(this.vin), { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(j => {
                if (!j.ok) { this.vinInfo = j.message; return; }
                this.vinInfo = [j.data.make, j.data.model, j.data.year, j.data.engine].filter(Boolean).join(' · ');
                if (j.make_id) this.makeId = String(j.make_id); if (j.data.year) this.year = j.data.year;
            });
        },
        beforeSubmit(e) {
            if (this.customerMode === 'new') { document.querySelector('input[name=customer_id]').value = ''; }
            if (this.vehicleMode === 'new' || this.customerMode === 'new') { const v = document.querySelector('input[name=vehicle_id]'); if (v) v.value = ''; }
        }
    };
}
</script>
@endpush
