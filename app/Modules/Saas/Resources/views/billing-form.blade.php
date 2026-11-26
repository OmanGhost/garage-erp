@extends('layouts.admin')
@section('title', __('New invoice'))
@section('content')
@php
    $plansJs = $plans->mapWithKeys(fn ($p) => [$p->id => ['monthly' => (float) $p->price_monthly, 'yearly' => (float) $p->price_yearly, 'name' => $p->name]]);
    $clientPlans = $clients->mapWithKeys(fn ($c) => [$c->id => $c->plan_id]);
    $oldItems = old('items', [['description' => '', 'qty' => 1, 'unit_price' => '']]);
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0"><i class="bi bi-receipt text-brand"></i> {{ __('New invoice') }}</h4>
    <a class="btn btn-outline-secondary" href="{{ route('admin.billing.index') }}"><i class="bi bi-arrow-right"></i> {{ __('Back') }}</a>
</div>

<form method="post" action="{{ route('admin.billing.store') }}"
      x-data="billingForm(@js($plansJs), @js($clientPlans), @js(array_values($oldItems)), {{ (float) old('tax_rate', $taxRate) }}, {{ (float) old('discount', 0) }})"
      x-init="type = @js(old('type', 'renewal')); company = @js((string) old('company_id', $companyId ?: '')); planId = @js((string) old('plan_id', '')); cycle = @js(old('billing_cycle', 'monthly')); paid = {{ old('paid') ? 'true' : 'false' }}; if (!planId) syncPlan()">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3"><div class="card-body row g-2">
                <div class="col-md-6"><label class="form-label small mb-0" for="f_company">{{ __('Client') }} *</label>
                    <select id="f_company" name="company_id" class="form-select" x-model="company" @change="syncPlan()" required>
                        <option value="">—</option>
                        @foreach($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}@if($c->plan) · {{ $c->plan->name }}@endif</option>@endforeach
                    </select></div>
                <div class="col-md-6"><label class="form-label small mb-0 d-block">{{ __('Invoice type') }}</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="type" id="t_ren" value="renewal" x-model="type"><label class="btn btn-outline-primary" for="t_ren"><i class="bi bi-arrow-repeat"></i> {{ __('Subscription renewal') }}</label>
                        <input type="radio" class="btn-check" name="type" id="t_cus" value="custom" x-model="type"><label class="btn btn-outline-primary" for="t_cus"><i class="bi bi-list-ul"></i> {{ __('Custom charges') }}</label>
                    </div></div>
                <template x-if="type === 'renewal'">
                    <div class="col-12 row g-2 m-0 p-0">
                        <div class="col-md-6"><label class="form-label small mb-0" for="f_plan">{{ __('Plan') }} *</label>
                            <select id="f_plan" name="plan_id" class="form-select" x-model="planId" required>
                                <option value="">—</option>
                                @foreach($plans as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                            </select></div>
                        <div class="col-md-6"><label class="form-label small mb-0" for="f_cycle">{{ __('Billing cycle') }}</label>
                            <select id="f_cycle" name="billing_cycle" class="form-select" x-model="cycle"><option value="monthly">{{ status_label('monthly') }}</option><option value="yearly">{{ status_label('yearly') }}</option></select></div>
                        <div class="col-12 small text-body-secondary"><i class="bi bi-info-circle"></i> {{ __('The subscription is extended by one cycle from its current end date and the plan line is added automatically.') }}
                            <span x-show="planId" class="fw-semibold" x-text="'— ' + fmt(planPrice())"></span></div>
                    </div>
                </template>
            </div></div>

            <div class="card mb-3">
                <div class="card-header d-flex align-items-center"><i class="bi bi-list-ul me-1"></i> <span x-text="type === 'renewal' ? @js(__('Additional lines (optional)')) : @js(__('Invoice lines'))"></span>
                    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" @click="items.push({description: '', qty: 1, unit_price: ''})"><i class="bi bi-plus"></i> {{ __('Add line') }}</button></div>
                <div class="card-body">
                    <template x-for="(it, i) in items" :key="i">
                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-12 col-md-6"><input class="form-control" :name="`items[${i}][description]`" x-model="it.description" placeholder="{{ __('Description') }}" aria-label="{{ __('Description') }}" :required="type === 'custom' && i === 0"></div>
                            <div class="col-4 col-md-2"><input type="number" step="0.001" min="0" class="form-control" :name="`items[${i}][qty]`" x-model="it.qty" placeholder="{{ __('Qty') }}" aria-label="{{ __('Qty') }}"></div>
                            <div class="col-5 col-md-3"><input type="number" step="0.001" min="0" class="form-control" :name="`items[${i}][unit_price]`" x-model="it.unit_price" placeholder="{{ __('Unit price') }}" aria-label="{{ __('Unit price') }}"></div>
                            <div class="col-3 col-md-1 text-end"><button type="button" class="btn btn-outline-danger" @click="items.splice(i, 1)" :disabled="items.length === 1 && type === 'custom'" aria-label="{{ __('Remove') }}"><i class="bi bi-trash"></i></button></div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="card"><div class="card-body"><label class="form-label small mb-0" for="f_notes">{{ __('Notes (printed on the invoice)') }}</label>
                <textarea id="f_notes" name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea></div></div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-body row g-2">
                <div class="col-6"><label class="form-label small mb-0" for="f_issue">{{ __('Issue date') }}</label><input id="f_issue" type="date" name="issue_date" class="form-control" value="{{ old('issue_date', today()->toDateString()) }}" required></div>
                <div class="col-6"><label class="form-label small mb-0" for="f_due">{{ __('Due date') }}</label><input id="f_due" type="date" name="due_date" class="form-control" value="{{ old('due_date', today()->addDays($dueDays)->toDateString()) }}" required></div>
                <div class="col-6"><label class="form-label small mb-0" for="f_disc">{{ __('Discount') }}</label><input id="f_disc" type="number" step="0.001" min="0" name="discount" class="form-control" x-model.number="discount"></div>
                <div class="col-6"><label class="form-label small mb-0" for="f_tax">{{ __('VAT %') }}</label><input id="f_tax" type="number" step="0.01" min="0" max="100" name="tax_rate" class="form-control" x-model.number="taxRate" required></div>
            </div>
            <ul class="list-group list-group-flush border-top">
                <li class="list-group-item d-flex"><span>{{ __('Subtotal') }}</span><span class="ms-auto font-monospace" x-text="fmt(subtotal())"></span></li>
                <li class="list-group-item d-flex text-danger"><span>{{ __('Discount') }}</span><span class="ms-auto font-monospace" x-text="'-' + fmt(disc())"></span></li>
                <li class="list-group-item d-flex"><span>{{ __('VAT') }} (<span x-text="taxRate"></span>%)</span><span class="ms-auto font-monospace" x-text="fmt(tax())"></span></li>
                <li class="list-group-item d-flex fw-bold fs-5"><span>{{ __('Total') }}</span><span class="ms-auto font-monospace" x-text="fmt(total())"></span></li>
            </ul></div>

            <div class="card mb-3"><div class="card-body">
                <div class="form-check form-switch"><input type="hidden" name="paid" value="0"><input class="form-check-input" type="checkbox" role="switch" id="f_paid" name="paid" value="1" x-model="paid"><label class="form-check-label" for="f_paid">{{ __('Already paid') }}</label></div>
                <div class="row g-2 mt-1" x-show="paid" x-cloak>
                    <div class="col-12"><label class="form-label small mb-0" for="f_method">{{ __('Payment method') }}</label><select id="f_method" name="payment_method" class="form-select">@foreach(\App\Modules\Core\Models\SubscriptionInvoice::METHODS as $m)<option value="{{ $m }}" @selected(old('payment_method') === $m)>{{ status_label($m) }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label small mb-0" for="f_ref">{{ __('Reference') }}</label><input id="f_ref" name="payment_reference" class="form-control" value="{{ old('payment_reference') }}"></div>
                </div>
            </div></div>
            <div class="d-grid"><button class="btn btn-primary btn-lg"><i class="bi bi-check2-circle"></i> {{ __('Issue invoice') }}</button></div>
        </div>
    </div>
</form>

<script>
    function billingForm(plans, clientPlans, items, taxRate, discount) {
        return {
            type: 'renewal', company: '', planId: '', cycle: 'monthly', paid: false, items, taxRate, discount,
            n(v) { const x = parseFloat(v); return isNaN(x) ? 0 : x; },
            fmt(v) { return (Math.round(v * 1000) / 1000).toFixed(3); },
            syncPlan() { if (clientPlans[this.company]) this.planId = String(clientPlans[this.company]); },
            planPrice() { const p = plans[this.planId]; return p ? p[this.cycle] : 0; },
            subtotal() {
                let s = this.items.reduce((a, it) => a + (String(it.description).trim() ? this.n(it.qty) * this.n(it.unit_price) : 0), 0);
                return this.type === 'renewal' ? s + this.planPrice() : s;
            },
            disc() { return Math.min(this.n(this.discount), this.subtotal()); },
            tax() { return Math.round((this.subtotal() - this.disc()) * this.n(this.taxRate) * 10) / 1000; },
            total() { return this.subtotal() - this.disc() + this.tax(); },
        };
    }
</script>
@endsection
