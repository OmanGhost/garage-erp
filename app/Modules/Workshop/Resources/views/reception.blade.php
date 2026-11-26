@extends('layouts.app')
@section('title', __('Vehicle Reception'))
@section('content')
@php
    // state after a failed submit: everything the user typed comes back (old input)
    $hasOld = session()->hasOldInput();
    $customerMode = old('customer_name') && ! old('customer_id') ? 'new' : 'existing';
    $vehicleMode = $customerMode === 'new' || (old('plate_number') && ! old('vehicle_id')) ? 'new' : 'existing';
    $oldDamages = collect(old('damages', []))->filter(fn ($d) => is_array($d))->map(fn ($d) => [
        'x' => $d['x'] ?? 0, 'y' => $d['y'] ?? 0, 'area' => $d['area'] ?? '', 'type' => $d['type'] ?? 'scratch', 'description' => $d['description'] ?? '',
    ])->values();
    $oldItems = collect(old('personal_items', []))->filter(fn ($v) => is_string($v))->values();
    $sel = fn (string $field, $value, $default = null) => (string) old($field, $default) === (string) $value ? 'selected' : '';
@endphp
<x-page-header :title="__('Vehicle Reception')" icon="bi-box-arrow-in-down" :breadcrumbs="[__('Workshop') => route('workshop.board'), __('Vehicle Reception')]"
               :subtitle="__('Register the customer and vehicle, record condition, belongings and signature, and open a job card.')"/>

<form method="post" action="{{ route('reception.store') }}" enctype="multipart/form-data" x-data="reception()" @submit="beforeSubmit" @pageshow.window="sending = false" novalidate>
    @csrf <x-idem/>
    @if($appointment)<input type="hidden" name="appointment_id" value="{{ $appointment->id }}">@endif
    {{-- refusals from the server stay on the page (the toast alone disappears) --}}
    @if(session('open_job_card'))
        @php $ojc = session('open_job_card'); @endphp
        <div class="alert alert-warning d-flex flex-wrap gap-3 align-items-center" role="alert" id="open-job-card-alert">
            <i class="bi bi-car-front-fill fs-3"></i>
            <div class="flex-grow-1">
                <div class="fw-semibold">{{ __('This vehicle is already in the workshop.') }}</div>
                <div class="small">{{ __('Job card :n · :s · opened :d', ['n' => $ojc['number'], 's' => $ojc['status'], 'd' => $ojc['date']]) }}</div>
                <div class="form-check mt-2 mb-0">
                    <input class="form-check-input" type="checkbox" name="allow_duplicate" value="1" id="allow_duplicate">
                    <label class="form-check-label small" for="allow_duplicate">{{ __('Open an additional job card for this vehicle anyway') }}</label>
                </div>
            </div>
            <a class="btn btn-warning" href="{{ $ojc['url'] }}"><i class="bi bi-box-arrow-up-right"></i> {{ __('Open the existing job card') }}</a>
        </div>
    @endif
    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card mb-3" id="sec-customer">
                <div class="card-header"><i class="bi bi-person me-1"></i>1. {{ __('Customer') }}</div>
                <div class="card-body">
                    <div class="btn-group mb-3" role="group">
                        <input type="radio" class="btn-check" id="cx" value="existing" x-model="customerMode"><label class="btn btn-outline-primary btn-sm" for="cx">{{ __('Existing customer') }}</label>
                        <input type="radio" class="btn-check" id="cn" value="new" x-model="customerMode"><label class="btn btn-outline-primary btn-sm" for="cn">{{ __('New customer') }}</label>
                    </div>
                    <div x-show="customerMode === 'existing'" @if($customerMode !== 'existing') x-cloak @endif>
                        <div @lookup-selected="customerId = $event.detail.id; vehicleMode = 'existing'">
                            <x-lookup name="customer_id" type="customers" :value="$customer?->id" :text="$customer ? $customer->name.' — '.$customer->phone : ''" :placeholder="__('Search by name, mobile or code...')" id="lk_customer_id"/>
                        </div>
                        @error('customer_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-2" x-show="customerMode === 'new'" @if($customerMode !== 'new') x-cloak @endif>
                        <div class="col-md-6"><label class="form-label required" for="customer_name">{{ __('Name') }}</label>
                            <input id="customer_name" name="customer_name" class="form-control @error('customer_name') is-invalid @enderror" value="{{ old('customer_name') }}" :disabled="customerMode !== 'new'">
                            @error('customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label required" for="customer_phone">{{ __('Mobile') }}</label>
                            <input id="customer_phone" name="customer_phone" class="form-control @error('customer_phone') is-invalid @enderror" dir="ltr" placeholder="+968 9xxxxxxx" value="{{ old('customer_phone') }}" :disabled="customerMode !== 'new'">
                            @error('customer_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label">{{ __('Email') }}</label>
                            <input type="email" name="customer_email" class="form-control @error('customer_email') is-invalid @enderror" dir="ltr" value="{{ old('customer_email') }}" :disabled="customerMode !== 'new'">
                            @error('customer_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label">{{ __('Type') }}</label><select name="customer_type" class="form-select" :disabled="customerMode !== 'new'">
                            @foreach(['individual' => 'Individual', 'company' => 'Company', 'government' => 'Government'] as $v => $l)<option value="{{ $v }}" {{ $sel('customer_type', $v) }}>{{ __($l) }}</option>@endforeach
                        </select></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3" id="sec-vehicle">
                <div class="card-header"><i class="bi bi-car-front me-1"></i>2. {{ __('Vehicle') }}</div>
                <div class="card-body">
                    <div class="btn-group mb-3">
                        <input type="radio" class="btn-check" id="vx" value="existing" x-model="vehicleMode" :disabled="customerMode === 'new'"><label class="btn btn-outline-primary btn-sm" for="vx">{{ __('Registered vehicle') }}</label>
                        <input type="radio" class="btn-check" id="vn" value="new" x-model="vehicleMode"><label class="btn btn-outline-primary btn-sm" for="vn">{{ __('New vehicle') }}</label>
                    </div>
                    <div x-show="vehicleMode === 'existing' && customerMode === 'existing'" @if($vehicleMode !== 'existing') x-cloak @endif>
                        <div @lookup-selected="odometer = $event.detail.extra?.odometer || odometer">
                            <x-lookup name="vehicle_id" type="vehicles" :value="$vehicle?->id" :text="$vehicle?->label() ?? ''" params="customer_id=#lk_customer_id" :placeholder="__('Search plate / VIN...')" id="lk_vehicle_id"/>
                        </div>
                        @error('vehicle_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('Only vehicles of the selected customer are listed.') }}</div>
                    </div>
                    <div class="row g-2" x-show="vehicleMode === 'new' || customerMode === 'new'" @if($vehicleMode !== 'new') x-cloak @endif>
                        <div class="col-md-3"><label class="form-label required" for="plate_number">{{ __('Plate number') }}</label>
                            <input id="plate_number" name="plate_number" class="form-control @error('plate_number') is-invalid @enderror" dir="ltr" value="{{ old('plate_number') }}">
                            @error('plate_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-2"><label class="form-label">{{ __('Code') }}</label><input name="plate_code" class="form-control text-uppercase" dir="ltr" maxlength="10" value="{{ old('plate_code') }}"></div>
                        <div class="col-md-2"><label class="form-label">{{ __('Country') }}</label><select name="plate_country" class="form-select">@foreach(['OM', 'AE', 'SA', 'QA', 'KW', 'BH'] as $c)<option {{ $sel('plate_country', $c, 'OM') }}>{{ $c }}</option>@endforeach</select></div>
                        <div class="col-md-5"><label class="form-label">{{ __('VIN (chassis)') }}</label>
                            <div class="input-group has-validation"><input name="vin" class="form-control text-uppercase @error('vin') is-invalid @enderror" dir="ltr" maxlength="17" x-model="vin">
                                <button type="button" class="btn btn-outline-secondary" @click="decodeVin" title="{{ __('Decode VIN') }}"><i class="bi bi-magic"></i></button>
                                @error('vin')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="form-text" x-text="vinInfo"></div>
                        </div>
                        <div class="col-md-4"><label class="form-label">{{ __('Make') }}</label>
                            <select name="vehicle_make_id" id="f_vehicle_make_id" class="form-select" x-model="makeId"><option value="">—</option>@foreach($makes as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">{{ __('Model') }}</label><x-lookup name="vehicle_model_id" type="vehicle_models" params="make_id=#f_vehicle_make_id" :value="$vehicleModel?->id" :text="$vehicleModel?->name ?? ''"/></div>
                        <div class="col-md-2"><label class="form-label">{{ __('Year') }}</label><input type="number" name="year" class="form-control @error('year') is-invalid @enderror" min="1950" max="{{ date('Y') + 1 }}" x-model="year"></div>
                        <div class="col-md-2"><label class="form-label">{{ __('Color') }}</label><input name="color" class="form-control" value="{{ old('color') }}"></div>
                        <div class="col-md-4"><label class="form-label">{{ __('Vehicle type') }}</label><select name="vehicle_type" class="form-select">@foreach(\App\Support\Options::VEHICLE_TYPES as $t)<option value="{{ $t }}" {{ $sel('vehicle_type', $t) }}>{{ status_label($t) }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">{{ __('Fuel') }}</label><select name="fuel_type" class="form-select"><option value="">—</option>@foreach(\App\Support\Options::FUEL_TYPES as $t)<option value="{{ $t }}" {{ $sel('fuel_type', $t) }}>{{ status_label($t) }}</option>@endforeach</select></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-clipboard-pulse me-1"></i>3. {{ __('Condition at reception') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label required" for="odometer">{{ __('Odometer (km)') }}</label>
                            <input type="number" id="odometer" name="odometer" class="form-control font-monospace @error('odometer') is-invalid @enderror" min="0" x-model="odometer">
                            @error('odometer')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-8"><label class="form-label required">{{ __('Fuel level') }}: <b x-text="fuel + '%'"></b></label>
                            <input type="range" class="form-range" name="fuel_level" min="0" max="100" step="12.5" x-model="fuel">
                            <div class="d-flex justify-content-between small text-body-secondary"><span>E</span><span>¼</span><span>½</span><span>¾</span><span>F</span></div></div>
                        <div class="col-12"><label class="form-label required" for="reported_issues">{{ __('Reported problems (customer complaint)') }}</label>
                            <textarea id="reported_issues" name="reported_issues" class="form-control @error('reported_issues') is-invalid @enderror" rows="3">{{ old('reported_issues') }}</textarea>
                            @error('reported_issues')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
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
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="checklist[{{ $k }}]" value="1" id="cl_{{ $k }}" @checked(old('checklist.'.$k))><label class="form-check-label" for="cl_{{ $k }}">{{ __($l) }}</label></div>
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
                    <div class="col-md-6"><label class="form-label">{{ __('Job type') }}</label><select name="job_type" class="form-select">@foreach(['repair', 'maintenance', 'warranty', 'insurance', 'internal'] as $t)<option value="{{ $t }}" {{ $sel('job_type', $t) }}>{{ status_label($t) }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Priority') }}</label><select name="priority" class="form-select">@foreach(\App\Support\Options::PRIORITIES as $p)<option value="{{ $p }}" {{ $sel('priority', $p, 'normal') }}>{{ status_label($p) }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Department') }}</label><select name="department_id" class="form-select"><option value="">—</option>@foreach($departments as $id => $n)<option value="{{ $id }}" {{ $sel('department_id', $id, $appointment?->department_id) }}>{{ $n }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Service advisor') }}</label><select name="service_advisor_id" class="form-select">@foreach($advisors as $id => $n)<option value="{{ $id }}" {{ $sel('service_advisor_id', $id, auth()->id()) }}>{{ $n }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label">{{ __('Expected delivery') }}</label>
                        <input type="datetime-local" name="expected_delivery_at" class="form-control @error('expected_delivery_at') is-invalid @enderror" value="{{ old('expected_delivery_at', now()->addDay()->setTime(17, 0)->format('Y-m-d\TH:i')) }}">
                        @error('expected_delivery_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label class="form-label">{{ __('Internal notes') }}</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-camera me-1"></i>5. {{ __('Photos (front, back, sides, interior, dashboard)') }}</div>
                <div class="card-body">
                    @if($hasOld)<div class="alert alert-warning small py-2"><i class="bi bi-info-circle"></i> {{ __('Your data was kept. For security, browsers do not keep selected photos: please attach them again.') }}</div>@endif
                    <input type="file" name="photos[]" class="form-control @if($errors->has('photos') || $errors->has('photos.*')) is-invalid @endif" accept="image/*" capture="environment" multiple @change="preparePhotos($event)">
                    @if($errors->has('photos') || $errors->has('photos.*'))<div class="invalid-feedback">{{ $errors->first('photos') ?: $errors->first('photos.*') }}</div>@endif
                    <div class="small text-body-secondary mt-1" x-show="photoBusy"><span class="spinner-border spinner-border-sm"></span> {{ __('Preparing photos...') }}</div>
                    <div class="d-flex flex-wrap gap-2 mt-2"><template x-for="p in previews"><img :src="p" class="rounded border" style="width:84px;height:64px;object-fit:cover"></template></div>
                    <div class="form-text">{{ __('Large photos are reduced automatically before upload.') }}</div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-pen me-1"></i>6. {{ __('Customer signature') }}</div>
                <div class="card-body">
                    <canvas id="sigPad" class="signature-pad"></canvas>
                    <input type="hidden" name="signature" id="sigInput" value="{{ str_starts_with((string) old('signature'), 'data:image/png;base64,') ? old('signature') : '' }}">
                    <div class="d-flex gap-2 mt-2">
                        <input name="customer_signed_name" class="form-control form-control-sm" placeholder="{{ __('Signed by (name)') }}" value="{{ old('customer_signed_name') }}">
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="pad && pad.clear(); document.getElementById('sigInput').value=''">{{ __('Clear') }}</button>
                    </div>
                    <div class="form-text">{{ __('The customer confirms the vehicle condition, belongings and authorizes inspection.') }}</div>
                </div>
            </div>
            <div class="card"><div class="card-body">
                {{-- why the reception was not saved: problems found before sending (browser) and reasons returned by the server --}}
                <div class="alert alert-warning small py-2 mb-2" x-show="problems.length" x-cloak role="alert" id="reception-problems">
                    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle"></i> {{ __('The form was not sent. Complete the following:') }}</div>
                    <ul class="mb-0 ps-3"><template x-for="p in problems" :key="p.msg"><li><a href="#" class="link-dark" @click.prevent="goTo(p.selector)" x-text="p.msg"></a></li></template></ul>
                </div>
                @if($errors->any() || session('error'))
                    <div class="alert alert-danger small py-2 mb-2" role="alert" id="reception-server-errors">
                        <div class="fw-semibold mb-1"><i class="bi bi-x-octagon"></i> {{ __('The vehicle was not received. Reason:') }}</div>
                        <ul class="mb-0 ps-3">
                            @if(session('error'))<li>{{ session('error') }}</li>@endif
                            @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                        </ul>
                    </div>
                @endif
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="notify" value="1" id="notify" @checked($hasOld ? old('notify') : true)><label class="form-check-label" for="notify">{{ __('Send reception confirmation to the customer (SMS/WhatsApp)') }}</label></div>
                <button class="btn btn-primary btn-lg w-100" :disabled="photoBusy || sending">
                    <span x-show="sending" class="spinner-border spinner-border-sm"></span>
                    <i class="bi bi-check2-circle" x-show="!sending"></i> {{ __('Receive vehicle & open job card') }}
                </button>
            </div></div>
        </div>
    </div>
</form>
@endsection
@push('scripts')
<script src="{{ asset('vendor/signature/signature_pad.umd.min.js') }}"></script>
<script>
function reception() {
    const T = {
        customer: @js(__('Select the customer, or choose “New customer” and enter the name and mobile.')),
        customerNew: @js(__('Enter the new customer name and mobile.')),
        vehicle: @js(__('Select the customer vehicle, or choose “New vehicle” and enter the plate number.')),
        plate: @js(__('Enter the plate number of the new vehicle.')),
        odometer: @js(__('Enter the odometer reading.')),
        issues: @js(__('Enter the problems reported by the customer.')),
        title: @js(__('Missing information')),
        phone: @js(__('Enter the new customer mobile number.')),
        photos: @js(__('Preparing photos...')),
    };
    return {
        customerMode: @js($customerMode), vehicleMode: @js($vehicleMode),
        customerId: @js($customer?->id), odometer: @js((string) old('odometer', '')), fuel: @js((float) old('fuel_level', 50)),
        damages: @js($oldDamages), items: @js($oldItems), previews: [], vin: @js((string) old('vin', '')), vinInfo: '',
        makeId: @js((string) old('vehicle_make_id', '')), year: @js((string) old('year', '')), pad: null, photoBusy: false, sending: false, problems: [],
        init() {
            this.$nextTick(() => {
                this.pad = erpSignature('sigPad', 'sigInput');
                const sig = document.getElementById('sigInput').value;
                if (this.pad && sig) this.pad.fromDataURL(sig);
                // after a failed submit: bring the first problem into view
                const bad = this.$el.querySelector('#open-job-card-alert, #reception-server-errors, .is-invalid, .invalid-feedback.d-block');
                if (bad) { bad.scrollIntoView({ block: 'center' }); bad.focus?.({ preventScroll: true }); }
            });
        },
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
        // phone cameras produce 3–8 MB photos: shrink them in the browser so uploads stay under the server limits
        async preparePhotos(e) {
            const input = e.target, files = Array.from(input.files || []);
            this.previews = files.map(f => URL.createObjectURL(f));
            if (!files.length || !window.DataTransfer) return;
            this.photoBusy = true;
            try {
                const out = new DataTransfer();
                for (const f of files) out.items.add(await this.shrink(f));
                input.files = out.files;
            } catch (err) { /* keep the original files */ }
            this.photoBusy = false;
        },
        shrink(file, max = 1600, quality = 0.82) {
            if (!file.type.startsWith('image/') || file.size < 900 * 1024) return Promise.resolve(file);
            return new Promise((resolve) => {
                const img = new Image(), url = URL.createObjectURL(file);
                img.onload = () => {
                    const scale = Math.min(1, max / Math.max(img.width, img.height));
                    const c = document.createElement('canvas');
                    c.width = Math.round(img.width * scale); c.height = Math.round(img.height * scale);
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                    URL.revokeObjectURL(url);
                    c.toBlob((b) => resolve(b && b.size < file.size ? new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file), 'image/jpeg', quality);
                };
                img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
                img.src = url;
            });
        },
        mark(selector, msg) {
            const el = selector && this.$el.querySelector(selector);
            if (!el) return;
            el.classList.add('is-invalid');
            const anchor = el.closest('.lookup, .input-group') || el;
            let fb = anchor.parentElement.querySelector(':scope > .client-feedback');
            if (!fb) { fb = document.createElement('div'); fb.className = 'invalid-feedback d-block client-feedback'; anchor.after(fb); }
            fb.textContent = msg;
            const clear = () => { el.classList.remove('is-invalid'); fb.remove(); this.problems = this.problems.filter((p) => p.selector !== selector); };
            el.addEventListener('input', clear, { once: true });
            el.closest('.lookup')?.addEventListener('lookup-selected', clear, { once: true });
        },
        goTo(selector) {
            const el = selector && this.$el.querySelector(selector);
            if (el) { el.scrollIntoView({ block: 'center' }); el.focus?.({ preventScroll: true }); }
        },
        beforeSubmit(e) {
            const val = (sel) => (this.$el.querySelector(sel)?.value || '').trim();
            const problems = [];
            const need = (ok, msg, selector) => { if (!ok) problems.push({ msg, selector }); };
            if (this.customerMode === 'new') {
                document.querySelector('input[name=customer_id]').value = '';
                need(val('#customer_name'), T.customerNew, '#customer_name');
                need(val('#customer_phone'), T.phone, '#customer_phone');
            } else {
                need(val('input[name=customer_id]'), T.customer, '#sec-customer .lookup input[type=text]');
            }
            if (this.vehicleMode === 'new' || this.customerMode === 'new') {
                const v = document.querySelector('input[name=vehicle_id]'); if (v) v.value = '';
                need(val('#plate_number'), T.plate, '#plate_number');
            } else {
                need(val('input[name=vehicle_id]'), T.vehicle, '#sec-vehicle .lookup input[type=text]');
            }
            need(String(this.odometer).trim() !== '', T.odometer, '#odometer');
            need(val('#reported_issues'), T.issues, '#reported_issues');
            this.problems = problems;
            if (problems.length) {
                e.preventDefault();
                problems.forEach((p) => this.mark(p.selector, p.msg));
                window.ERP?.toast?.(problems.map((p) => p.msg), 'warning', { title: T.title, timeout: 9000 });
                this.goTo(problems[0].selector);
                return false;
            }
            if (this.photoBusy) { e.preventDefault(); window.ERP?.toast?.(T.photos, 'info'); return false; }
            this.sending = true;
        }
    };
}
</script>
@endpush
