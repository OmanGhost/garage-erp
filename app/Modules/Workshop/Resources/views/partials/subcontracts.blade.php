{{-- Outside (subcontracted) work on the job card --}}
@if(tenant()->company()?->moduleEnabled('subcontracting') && auth()->user()->can('subcontracts.view'))
    @php
        $subs = $jc->subcontracts()->with('supplier', 'supplierInvoice')->latest('id')->get();
        $subSuppliers = auth()->user()->can('subcontracts.create') && ! $closed ? \App\Modules\Purchasing\Models\Supplier::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id') : collect();
    @endphp
    <div class="border-top">
        <div class="card-body pb-2 d-flex align-items-center">
            <h6 class="mb-0"><i class="bi bi-buildings me-1"></i>{{ __('Outside work') }}</h6>
            @if($subSuppliers->isNotEmpty())
                <button type="button" class="btn btn-sm btn-outline-primary ms-auto" data-bs-toggle="collapse" data-bs-target="#subNew"><i class="bi bi-plus-lg"></i> {{ __('Send work outside') }}</button>
            @endif
        </div>
        @if($subs->isNotEmpty())
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Subcontractor') }}</th><th>{{ __('Work') }}</th><th>{{ __('Expected back') }}</th><th class="text-end">{{ __('Cost') }}</th><th class="text-end">{{ __('Customer price') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($subs as $o)
                    <tr class="{{ $o->isOverdue() ? 'table-danger' : '' }}">
                        <td class="fw-semibold">{{ $o->number }}</td>
                        <td>{{ $o->supplier?->name }}</td>
                        <td>{{ $o->description }}@if($o->item_sent)<div class="small text-body-secondary">{{ __('Item sent') }}: {{ $o->item_sent }}</div>@endif</td>
                        <td class="small">{{ $o->expected_at?->format('Y-m-d') ?? '—' }}@if($o->warranty_until)<div class="text-success"><i class="bi bi-shield-check"></i> {{ $o->warranty_until->format('Y-m-d') }}</div>@endif</td>
                        <td class="text-end font-monospace">{{ money($o->actual_cost ?? $o->quoted_cost, false) }}</td>
                        <td class="text-end font-monospace">{{ money($o->sell_price, false) }}</td>
                        <td>{!! status_badge($o->status) !!}</td>
                        <td class="text-end text-nowrap">@include('workshop::subcontracts._actions', ['o' => $o])</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        @if($subSuppliers->isNotEmpty())
            <form method="post" action="{{ route('subcontracts.store', $jc) }}" class="collapse card-body border-top row g-2 align-items-end" id="subNew">@csrf
                <div class="col-md-3"><label class="form-label small">{{ __('Subcontractor') }}</label><select name="supplier_id" class="form-select form-select-sm" required>@foreach($subSuppliers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label small">{{ __('Work type') }}</label><select name="work_type" class="form-select form-select-sm">@foreach(\App\Modules\Workshop\Models\SubcontractOrder::WORK_TYPES as $wt)<option value="{{ $wt }}">{{ __(ucfirst($wt)) }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label small">{{ __('Work required') }}</label><input name="description" class="form-control form-control-sm" required maxlength="190"></div>
                <div class="col-md-3"><label class="form-label small">{{ __('Item sent (customer property)') }}</label><input name="item_sent" class="form-control form-control-sm" maxlength="190"></div>
                <div class="col-md-2"><label class="form-label small">{{ __('Agreed cost') }}</label><input name="quoted_cost" type="number" step="0.001" min="0" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><label class="form-label small">{{ __('Customer price') }}</label><input name="sell_price" type="number" step="0.001" min="0" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><label class="form-label small">{{ __('Expected back') }}</label><input name="expected_at" type="date" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label class="form-label small">{{ __('Warranty (days)') }}</label><input name="warranty_days" type="number" min="0" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-3"><label class="form-label small">{{ __('Notes') }}</label><input name="notes" class="form-control form-control-sm" maxlength="1000"></div>
                <div class="col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i></button></div>
                @can('job_cards.approve_additional')<div class="col-12"><div class="form-check small"><input class="form-check-input" type="checkbox" name="pre_approved" value="1" id="subPre"><label class="form-check-label" for="subPre">{{ __('Customer already approved (by phone)') }}</label></div></div>@endcan
                <div class="col-12 form-text">{{ __('The customer price is added to the job card as a task. The item leaves without a stock movement.') }}</div>
            </form>
        @endif
    </div>
@endif
