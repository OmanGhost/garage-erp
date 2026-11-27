@extends('layouts.app')
@section('title', $jc->number)
@section('content')
@php
    $pendingTasks = $jc->tasks->where('approval_status', 'pending');
    $pendingParts = $jc->parts->where('approval_status', 'pending');
    $closed = $jc->isClosed();
    $postedInvoices = $jc->invoices->whereIn('status', ['posted', 'partially_paid', 'paid']);
@endphp
<x-page-header :title="$jc->number" icon="bi-clipboard-check" :breadcrumbs="[__('Job Cards') => route('crud.index', 'job-cards'), $jc->number]"
    :subtitle="'<span class=\'plate-badge\'><span class=\'flag\'>'.e($jc->vehicle->plate_country).'</span>'.e($jc->vehicle->plate()).'</span> '.e($jc->vehicle->title()).' · '.e($jc->customer->name).' '.e($jc->customer->phone).' · '.status_badge($jc->status).' '.status_badge($jc->priority)">
    @if(session('print_receipt'))<a href="{{ session('print_receipt') }}" target="_blank" class="btn btn-warning"><i class="bi bi-printer"></i> {{ __('Print reception receipt') }}</a>@endif
    @if(in_array($jc->status, ['delivered', 'completed'], true))
        @can('job_cards.print')<a href="{{ route('job-cards.gate-pass', $jc) }}" target="_blank" rel="noopener" class="btn btn-success"><i class="bi bi-door-open"></i> {{ __('Print gate pass') }}</a>@endcan
    @endif
    @can('job_cards.change_status')
        @if($next)
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-arrow-right-circle"></i> {{ __('Change status') }}</button>
            <div class="dropdown-menu dropdown-menu-end p-2 shadow" style="min-width:280px">
                @foreach($next as $s)
                    @if($s === 'delivered')
                        {{-- delivery asks who collects the vehicle (owner or authorized person) --}}
                        <button type="button" class="dropdown-item rounded mb-1" data-bs-toggle="modal" data-bs-target="#deliverModal"><span class="badge-dot me-2" style="background:{{ \App\Modules\Workshop\Services\JobCardWorkflow::COLORS[$s] }}"></span>{{ status_label($s) }}</button>
                        @continue
                    @endif
                    <form method="post" action="{{ route('job-cards.status', $jc) }}" class="mb-1" @if($s === 'cancelled') data-confirm="{{ __('Cancel this job card?') }}" @endif>@csrf <x-idem/>
                        <input type="hidden" name="status" value="{{ $s }}">
                        <button class="dropdown-item rounded {{ $s === 'cancelled' ? 'text-danger' : '' }}"><span class="badge-dot me-2" style="background:{{ \App\Modules\Workshop\Services\JobCardWorkflow::COLORS[$s] }}"></span>{{ status_label($s) }}</button>
                    </form>
                @endforeach
            </div>
        </div>
        @endif
    @endcan
    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i> {{ __('More') }}</button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><a class="dropdown-item" href="{{ route('job-cards.print', $jc) }}" target="_blank"><i class="bi bi-printer me-2"></i>{{ __('Print job card') }}</a></li>
            <li><a class="dropdown-item" href="{{ route('reception.receipt', $jc) }}" target="_blank"><i class="bi bi-qr-code me-2"></i>{{ __('Reception receipt') }}</a></li>
            @can('job_cards.print')<li><a class="dropdown-item" href="{{ route('job-cards.gate-pass', $jc) }}" target="_blank" rel="noopener"><i class="bi bi-door-open me-2"></i>{{ __('Gate pass') }}</a></li>@endcan
            @can('handover_authorizations.create')<li><a class="dropdown-item" href="{{ route('handover-authorizations.create', ['job_card_id' => $jc->id]) }}"><i class="bi bi-person-badge me-2"></i>{{ __('Authorize someone to collect the vehicle') }}</a></li>@endcan
            @can('quotations.create')<li><a class="dropdown-item" href="{{ route('quotations.sales.create', ['job_card_id' => $jc->id]) }}"><i class="bi bi-file-earmark-plus me-2"></i>{{ __('New quotation') }}</a></li>@endcan
            @can('purchase_requests.create')<li><form method="post" action="{{ route('job-cards.purchase-request', $jc) }}">@csrf <x-idem/><button class="dropdown-item"><i class="bi bi-cart-plus me-2"></i>{{ __('Request missing parts (purchase)') }}</button></form></li>@endcan
            @can('invoices.create')@unless($closed)<li><form method="post" action="{{ route('job-cards.invoice', $jc) }}">@csrf <x-idem/><button class="dropdown-item"><i class="bi bi-receipt me-2"></i>{{ __('Prepare invoice') }}</button></form></li>@endunless @endcan
            @if($jc->checkIn)<li><a class="dropdown-item" href="{{ route('public.track', $jc->checkIn->receipt_token) }}" target="_blank"><i class="bi bi-link-45deg me-2"></i>{{ __('Customer tracking page') }}</a></li>@endif
        </ul>
    </div>
</x-page-header>

{{-- delivery: who collects the vehicle --}}
@if(in_array('delivered', $next ?? [], true))
<div class="modal fade" id="deliverModal" tabindex="-1" aria-labelledby="deliverModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="{{ route('job-cards.status', $jc) }}" x-data="{ receiver: '{{ old('receiver', 'owner') }}' }">@csrf <x-idem/>
            <input type="hidden" name="status" value="delivered">
            <div class="modal-header"><h5 class="modal-title" id="deliverModalTitle"><i class="bi bi-key"></i> {{ __('Deliver the vehicle') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body">
                <div class="small text-body-secondary mb-2">{{ __('Who is collecting the vehicle?') }}</div>
                <div class="btn-group w-100 mb-3" role="group">
                    <input type="radio" class="btn-check" name="receiver" id="rcv_owner" value="owner" x-model="receiver"><label class="btn btn-outline-primary" for="rcv_owner"><i class="bi bi-person"></i> {{ __('The owner') }}</label>
                    <input type="radio" class="btn-check" name="receiver" id="rcv_auth" value="authorized" x-model="receiver"><label class="btn btn-outline-primary" for="rcv_auth"><i class="bi bi-person-badge"></i> {{ __('Authorized person') }}</label>
                </div>
                <div x-show="receiver === 'owner'">
                    <div class="row g-2">
                        <div class="col-7"><label class="form-label small mb-0" for="rcv_name">{{ __('Name') }}</label><input id="rcv_name" name="receiver_name" class="form-control" value="{{ $jc->customer->name }}" :disabled="receiver !== 'owner'"></div>
                        <div class="col-5"><label class="form-label small mb-0" for="rcv_oid">{{ __('ID number (optional)') }}</label><input id="rcv_oid" name="receiver_id_number" class="form-control" dir="ltr" :disabled="receiver !== 'owner'"></div>
                    </div>
                </div>
                <div x-show="receiver === 'authorized'" x-cloak>
                    @if($pickupAuths->isEmpty())
                        <div class="alert alert-warning small mb-2"><i class="bi bi-exclamation-triangle"></i> {{ __('There is no active pick-up authorization for this vehicle. The vehicle can only be handed to the owner.') }}</div>
                    @else
                        <label class="form-label small mb-0" for="rcv_auth_id">{{ __('Authorization') }}</label>
                        <select id="rcv_auth_id" name="handover_authorization_id" class="form-select mb-2" :disabled="receiver !== 'authorized'">
                            @foreach($pickupAuths as $pa)<option value="{{ $pa->id }}">{{ $pa->number }} — {{ $pa->person_name }} ({{ status_label((string) $pa->relationship ?: 'other') }})</option>@endforeach
                        </select>
                        <label class="form-label small mb-0 required" for="rcv_aid">{{ __('ID number shown by the person') }}</label>
                        <input id="rcv_aid" name="receiver_id_number" class="form-control font-monospace" dir="ltr" autocomplete="off" :disabled="receiver !== 'authorized'" :required="receiver === 'authorized'">
                        <div class="form-text">{{ __('Type the number from the original ID card. It must match the authorization, otherwise the delivery is refused.') }}</div>
                    @endif
                    @can('handover_authorizations.create')<a class="small" href="{{ route('handover-authorizations.create', ['job_card_id' => $jc->id]) }}"><i class="bi bi-plus"></i> {{ __('New authorization') }}</a>@endcan
                </div>
                <label class="form-label small mb-0 mt-3" for="rcv_notes">{{ __('Notes') }}</label><input id="rcv_notes" name="notes" class="form-control" maxlength="250">
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-success"><i class="bi bi-check2-circle"></i> {{ __('Confirm delivery') }}</button></div>
        </form>
    </div></div>
</div>
@endif

@include('workshop::partials.guide')

@if($pendingTasks->isNotEmpty() || $pendingParts->isNotEmpty())
<div class="alert alert-warning">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-hourglass-split fs-5"></i>
        <strong>{{ __('Additional work waiting for customer approval') }}</strong>
        <span class="ms-auto d-flex gap-2">
            @can('quotations.create')<form method="post" action="{{ route('job-cards.additional-quote', $jc) }}">@csrf <x-idem/><button class="btn btn-sm btn-warning"><i class="bi bi-send"></i> {{ __('Send approval request') }}</button></form>@endcan
            @can('job_cards.approve_additional')<button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#additionalModal">{{ __('Record decision') }}</button>@endcan
        </span>
    </div>
</div>
@endif

<div class="row g-3">
    <div class="col-xl-8">
        <ul class="nav nav-tabs mb-0" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-tasks"><i class="bi bi-tools"></i> {{ __('Services & tasks') }} <span class="badge text-bg-secondary">{{ $jc->tasks->count() }}</span></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-parts"><i class="bi bi-gear"></i> {{ __('Spare parts') }} <span class="badge text-bg-secondary">{{ $jc->parts->count() }}</span></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-insp"><i class="bi bi-search"></i> {{ __('Inspection & quotations') }}</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-notes"><i class="bi bi-chat-text"></i> {{ __('Notes & photos') }}</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cost"><i class="bi bi-calculator"></i> {{ __('Costing') }}</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-hist"><i class="bi bi-clock-history"></i> {{ __('History') }}</button></li>
        </ul>
        <div class="tab-content card border-top-0 rounded-top-0 mb-3">
            {{-- tasks --}}
            <div class="tab-pane fade show active" id="tab-tasks">
                <div class="table-responsive"><table class="table table-erp align-middle mb-0">
                    <thead><tr><th>{{ __('Task') }}</th><th>{{ __('Technician') }}</th><th>{{ __('Time') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($jc->tasks as $t)
                        <tr class="{{ $t->approval_status === 'pending' ? 'table-warning' : ($t->status === 'cancelled' ? 'text-decoration-line-through text-body-tertiary' : '') }}">
                            <td><div class="fw-semibold">{{ $t->description }}</div><div class="small text-body-secondary">{{ $t->department?->displayName() }} @if($t->is_additional)<span class="badge text-bg-warning">{{ __('Additional') }}</span>@endif @if($t->is_subcontract)<span class="badge text-bg-info"><i class="bi bi-buildings"></i> {{ __('Outside work') }}</span>@endif @if($t->approval_status !== 'approved'){!! status_badge($t->approval_status) !!}@endif</div>
                                @if($t->scheduled_start && ! in_array($t->status, ['done', 'cancelled']))<div class="small {{ $t->scheduled_end->isPast() ? 'text-danger' : 'text-primary' }}"><i class="bi bi-calendar-range"></i> {{ $t->scheduled_start->format($t->scheduled_start->isToday() ? 'H:i' : 'd/m H:i') }}–{{ $t->scheduled_end->format('H:i') }}@if($t->bay) · {{ $t->bay->code }}@endif</div>@endif</td>
                            <td>
                                @if(!$closed && $t->status !== 'done' && auth()->user()->can('job_cards.update'))
                                    <form method="post" action="{{ route('job-cards.tasks.action', [$t, 'assign']) }}">@csrf
                                        <select name="technician_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">—</option>@foreach($technicians as $id => $n)<option value="{{ $id }}" @selected($t->technician_id === $id)>{{ $n }}</option>@endforeach</select>
                                    </form>
                                @else {{ $t->technician?->name ?? '—' }} @endif
                            </td>
                            <td class="small text-nowrap">{{ $t->actual_minutes }} / {{ $t->estimated_minutes }} {{ __('min') }}</td>
                            <td class="text-end font-monospace">{{ money($t->line_total, false) }}</td>
                            <td>{!! status_badge($t->status) !!}</td>
                            <td class="text-end text-nowrap">
                                @if(!$closed && $t->approval_status === 'approved' && ! $t->is_subcontract)
                                    @if(in_array($t->status, ['pending', 'paused']))<form method="post" action="{{ route('job-cards.tasks.action', [$t, 'start']) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success" title="{{ __('Start') }}"><i class="bi bi-play-fill"></i></button></form>@endif
                                    @if($t->status === 'in_progress')
                                        <form method="post" action="{{ route('job-cards.tasks.action', [$t, 'pause']) }}" class="d-inline">@csrf<button class="btn btn-sm btn-warning" title="{{ __('Pause') }}"><i class="bi bi-pause-fill"></i></button></form>
                                        <form method="post" action="{{ route('job-cards.tasks.action', [$t, 'finish']) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary" title="{{ __('Finish') }}"><i class="bi bi-check-lg"></i></button></form>
                                    @endif
                                @endif
                                @if(!$closed && !in_array($t->status, ['done', 'cancelled']) && ! $t->is_subcontract)<form method="post" action="{{ route('job-cards.tasks.action', [$t, 'cancel']) }}" class="d-inline" data-confirm="{{ __('Cancel this task?') }}">@csrf<button class="btn btn-sm btn-light text-danger"><i class="bi bi-x"></i></button></form>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-tools"></i>{{ __('No tasks yet. Add services from the catalog or convert an approved quotation.') }}</div></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                @if(!$closed)
                @can('job_cards.update')
                <form method="post" action="{{ route('job-cards.tasks.store', $jc) }}" class="card-body border-top row g-2 align-items-end" x-data="{price:'', minutes:''}">@csrf <x-idem/>
                    <div class="col-md-4" @lookup-selected="price = $event.detail.extra.price; minutes = $event.detail.extra.minutes"><label class="form-label small">{{ __('Service') }}</label><x-lookup name="service_id" type="services" size="sm" :params="'vehicle_type='.$jc->vehicle->vehicle_type"/></div>
                    <div class="col-md-3"><label class="form-label small">{{ __('Or description') }}</label><input name="description" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><label class="form-label small">{{ __('Technician') }}</label><select name="technician_id" class="form-select form-select-sm"><option value="">—</option>@foreach($technicians as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
                    <div class="col-md-1"><label class="form-label small">{{ __('Price') }}</label><input name="unit_price" type="number" step="0.001" min="0" class="form-control form-control-sm" x-model="price"></div>
                    <div class="col-md-1"><label class="form-label small">{{ __('Min') }}</label><input name="estimated_minutes" type="number" min="0" class="form-control form-control-sm" x-model="minutes"></div>
                    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i></button></div>
                    @can('job_cards.approve_additional')<div class="col-12"><div class="form-check small"><input class="form-check-input" type="checkbox" name="pre_approved" value="1" id="preA"><label class="form-check-label" for="preA">{{ __('Customer already approved (additional work)') }}</label></div></div>@endcan
                </form>
                @endcan
                @endif
                @include('workshop::partials.subcontracts')
            </div>

            {{-- parts --}}
            <div class="tab-pane fade" id="tab-parts">
                <div class="table-responsive"><table class="table table-erp align-middle mb-0">
                    <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Req.') }}</th><th>{{ __('Reserved') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Returned') }}</th><th class="text-end">{{ __('Price') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($jc->parts as $p)
                        <tr class="{{ $p->approval_status === 'pending' ? 'table-warning' : '' }}">
                            <td><div class="fw-semibold">{{ $p->description }}</div><div class="small text-body-secondary">{{ $p->part->sku }} @if($p->part_condition && $p->part_condition !== 'new')<span class="badge text-bg-info">{{ status_label($p->part_condition) }}</span>@endif @if($p->is_additional)<span class="badge text-bg-warning">{{ __('Additional') }}</span>@endif</div></td>
                            <td class="small">{{ $p->warehouse?->displayName() }}</td>
                            <td>{{ qty($p->qty) }}</td><td>{{ qty($p->qty_reserved) }}</td><td>{{ qty($p->qty_issued) }}</td><td>{{ qty($p->qty_returned) }}</td>
                            <td class="text-end font-monospace">{{ money($p->unit_price, false) }}</td>
                            <td>{!! status_badge($p->status) !!} @if($p->approval_status !== 'approved'){!! status_badge($p->approval_status) !!}@endif</td>
                            <td class="text-end text-nowrap">
                                @if(!$closed && $p->approval_status === 'approved' && \App\Support\Money::cmp($p->qty_issued, $p->qty) < 0 && $p->status !== 'cancelled')
                                    @can('stock.issue')
                                    <form method="post" action="{{ route('job-cards.parts.action', [$p, 'issue']) }}" class="d-inline-flex gap-1">@csrf <x-idem/>
                                        <input type="number" name="qty" step="0.001" min="0.001" max="{{ (float) $p->qty - (float) $p->qty_issued }}" value="{{ (float) $p->qty - (float) $p->qty_issued }}" class="form-control form-control-sm" style="width:70px">
                                        <button class="btn btn-sm btn-success" title="{{ __('Issue from stock') }}"><i class="bi bi-box-arrow-right"></i></button>
                                    </form>
                                    @endcan
                                @endif
                                @if(!$closed && \App\Support\Money::isPositive($p->netQty()) && $postedInvoices->isEmpty())
                                    @can('stock.issue')
                                    <form method="post" action="{{ route('job-cards.parts.action', [$p, 'return']) }}" class="d-inline-flex gap-1" data-confirm="{{ __('Return to stock?') }}">@csrf <x-idem/>
                                        <input type="number" name="qty" step="0.001" min="0.001" max="{{ (float) $p->netQty() }}" value="{{ (float) $p->netQty() }}" class="form-control form-control-sm" style="width:70px">
                                        <button class="btn btn-sm btn-outline-secondary" title="{{ __('Return to stock') }}"><i class="bi bi-box-arrow-in-left"></i></button>
                                    </form>
                                    @endcan
                                @endif
                                @if(!$closed && \App\Support\Money::isZero($p->netQty()) && $p->status !== 'cancelled')
                                    <form method="post" action="{{ route('job-cards.parts.action', [$p, 'cancel']) }}" class="d-inline" data-confirm="{{ __('Cancel this part request?') }}">@csrf<button class="btn btn-sm btn-light text-danger"><i class="bi bi-x"></i></button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty-state"><i class="bi bi-gear"></i>{{ __('No parts requested yet.') }}</div></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                @if(!$closed)
                @can('job_cards.update')
                <form method="post" action="{{ route('job-cards.parts.store', $jc) }}" class="card-body border-top row g-2 align-items-end" x-data="{price:''}">@csrf <x-idem/>
                    <div class="col-md-5" @lookup-selected="price = $event.detail.extra.workshop_price > 0 ? $event.detail.extra.workshop_price : $event.detail.extra.sale_price"><label class="form-label small">{{ __('Part (name / SKU / OEM / barcode)') }}</label><x-lookup name="part_id" type="parts" size="sm" required/></div>
                    <div class="col-md-3"><label class="form-label small">{{ __('Warehouse') }}</label><select name="warehouse_id" class="form-select form-select-sm">@foreach($warehouses as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
                    <div class="col-md-1"><label class="form-label small">{{ __('Qty') }}</label><input name="qty" type="number" step="0.001" min="0.001" value="1" class="form-control form-control-sm" required></div>
                    <div class="col-md-2"><label class="form-label small">{{ __('Price') }}</label><input name="unit_price" type="number" step="0.001" min="0" class="form-control form-control-sm" x-model="price"></div>
                    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i></button></div>
                    @can('job_cards.approve_additional')<div class="col-12"><div class="form-check small"><input class="form-check-input" type="checkbox" name="pre_approved" value="1" id="preP"><label class="form-check-label" for="preP">{{ __('Customer already approved (additional work)') }}</label></div></div>@endcan
                </form>
                @endcan
                @endif
            </div>

            {{-- inspection & quotations --}}
            <div class="tab-pane fade" id="tab-insp"><div class="card-body">
                <h6>{{ __('Inspections') }}</h6>
                <div class="list-group mb-3">
                    @forelse($jc->inspections as $i)
                        <a href="{{ route('inspections.show', $i) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <span class="badge {{ $i->stage === 'after' ? 'text-bg-success' : 'text-bg-primary' }}">{{ $i->stage === 'after' ? __('After repair') : __('Before repair') }}</span>
                            <span>{{ $i->started_at?->format('Y-m-d H:i') }} · {{ $i->technician?->name }}</span>
                            @if($i->customer_viewed_at)<span class="small text-success"><i class="bi bi-eye"></i> {{ __('Seen by customer') }}</span>@elseif($i->sent_at)<span class="small text-body-secondary"><i class="bi bi-send"></i> {{ __('Sent') }}</span>@endif
                            <span class="ms-auto">{!! status_badge($i->status) !!}</span></a>
                    @empty
                        <div class="text-body-secondary small mb-2">{{ __('No inspection yet.') }}</div>
                    @endforelse
                </div>
                @if(!$closed)
                @can('inspections.create')
                <form method="post" action="{{ route('inspections.start', $jc) }}" class="row g-2 mb-4">@csrf
                    <div class="col-md-5"><select name="template_id" class="form-select form-select-sm"><option value="">{{ __('Automatic checklist') }}</option>@foreach($templates as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
                    <div class="col-md-4"><select name="technician_id" class="form-select form-select-sm">@foreach($technicians as $id => $n)<option value="{{ $id }}" @selected($id === auth()->id())>{{ $n }}</option>@endforeach</select></div>
                    <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i> {{ __('Start inspection') }}</button></div>
                </form>
                @if(tenant()->company()?->moduleEnabled('dvi') && $jc->inspections->where('stage', 'before')->where('status', 'completed')->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        @if($jc->inspections->where('stage', 'after')->isEmpty())
                            <form method="post" action="{{ route('inspections.start', $jc) }}">@csrf<input type="hidden" name="stage" value="after"><button class="btn btn-sm btn-outline-success"><i class="bi bi-clipboard2-check"></i> {{ __('Start after-repair inspection') }}</button></form>
                        @endif
                    </div>
                @endif
                @endcan
                @endif
                @if(tenant()->company()?->moduleEnabled('dvi') && $jc->inspections->where('stage', 'after')->isNotEmpty() && $jc->inspections->where('stage', 'before')->isNotEmpty())
                    <a href="{{ route('inspections.compare', $jc) }}" class="btn btn-sm btn-outline-secondary mb-4"><i class="bi bi-layout-split"></i> {{ __('Compare before / after repair') }}</a>
                @endif
                <h6>{{ __('Quotations') }}</h6>
                <div class="list-group">
                    @forelse($jc->quotations as $q)
                        <a href="{{ route('quotations.show', $q) }}" class="list-group-item list-group-item-action d-flex gap-2">
                            <span>{{ $q->number }} v{{ $q->version }} @if($q->is_supplementary)<span class="badge text-bg-warning">{{ __('Additional') }}</span>@endif</span>
                            <span class="ms-auto font-monospace">{{ money($q->total, false) }}</span>{!! status_badge($q->status) !!}</a>
                    @empty
                        <div class="text-body-secondary small">{{ __('No quotations yet.') }}</div>
                    @endforelse
                </div>
            </div></div>

            {{-- notes --}}
            <div class="tab-pane fade" id="tab-notes"><div class="card-body">
                <form method="post" action="{{ route('job-cards.notes.store', $jc) }}" enctype="multipart/form-data" class="mb-3">@csrf <x-idem/>
                    <textarea name="note" class="form-control mb-2" rows="2" placeholder="{{ __('Write a progress note...') }}" required></textarea>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <input type="file" name="photos[]" multiple class="form-control form-control-sm w-auto" accept="image/*,video/*" capture="environment">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="visible_to_customer" value="1" id="vtc"><label class="form-check-label small" for="vtc">{{ __('Visible to customer') }}</label></div>
                        <button class="btn btn-sm btn-primary ms-auto"><i class="bi bi-send"></i> {{ __('Add note') }}</button>
                    </div>
                </form>
                <ul class="timeline">
                    @foreach($jc->notes->sortByDesc('id') as $n)
                        <li><div class="small text-body-secondary">{{ $n->user?->name }} · {{ $n->created_at->format('Y-m-d H:i') }} @if($n->visible_to_customer)<i class="bi bi-eye text-success" title="{{ __('Visible to customer') }}"></i>@endif</div><div>{!! nl2br(e($n->note)) !!}</div></li>
                    @endforeach
                </ul>
            </div>
            <x-attachments :model="$jc" :title="__('Photos & videos')" />
            </div>

            {{-- costing --}}
            <div class="tab-pane fade" id="tab-cost"><div class="card-body">
                @can('reports.view')
                <div class="row g-3 text-center">
                    @foreach([['estimated_total', __('Estimate (incl. VAT)')], ['labor_cost', __('Labour cost')], ['parts_cost', __('Parts cost')], ...((float) $jc->subcontract_cost > 0 ? [['subcontract_cost', __('Outside work cost')]] : []), ['total_cost', __('Total cost')], ['revenue', __('Net revenue')], ['profit', __('Profit')]] as [$k, $l])
                        <div class="col-6 col-md-4"><div class="border rounded p-2"><div class="small text-body-secondary">{{ $l }}</div><div class="fs-5 fw-bold font-monospace {{ $k === 'profit' ? ((float) $jc->profit >= 0 ? 'text-success' : 'text-danger') : '' }}">{{ money($jc->{$k}, false) }}</div></div></div>
                    @endforeach
                </div>
                @if((float) $jc->revenue > 0)<div class="text-center mt-2 small">{{ __('Margin') }}: <b>{{ round((float) $jc->profit * 100 / (float) $jc->revenue, 1) }}%</b></div>@endif
                @else
                    <div class="text-body-secondary">{{ __('You do not have permission to view costing.') }}</div>
                @endcan
            </div></div>

            {{-- history --}}
            <div class="tab-pane fade" id="tab-hist"><div class="card-body">
                <ul class="timeline">
                    @foreach($jc->histories->sortByDesc('id') as $h)
                        <li><div class="fw-semibold">{{ $h->from_status ? status_label($h->from_status).' → ' : '' }}{{ status_label($h->to_status) }}</div>
                            <div class="small text-body-secondary">{{ $h->user?->name }} · {{ $h->created_at?->format('Y-m-d H:i') }} @if($h->notes)· {{ $h->notes }}@endif</div></li>
                    @endforeach
                </ul>
            </div></div>
        </div>
    </div>

    <div class="col-xl-4">
        @php
            $modPlan = tenant()->company()?->moduleEnabled('planning');
            $modTrack = tenant()->company()?->moduleEnabled('vehicle_tracking');
        @endphp
        @if(! $closed && ($modPlan || $modTrack))
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center"><i class="bi bi-calendar-range me-1"></i>{{ __('Planning & tracking') }}
                @can('workshop_planning.view')<a href="{{ route('workshop.planner') }}" class="ms-auto small">{{ __('Planner') }}</a>@endcan</div>
            <div class="card-body small">
                @if($modPlan)
                @php $eta = $jc->estimated_completion_at ?? app(\App\Modules\Workshop\Services\CapacityService::class)->forecast($jc); @endphp
                <div class="d-flex mb-1"><span class="text-body-secondary">{{ __('Estimated completion') }}</span><b class="ms-auto {{ $eta && $jc->promised_at && $eta->gt($jc->promised_at) ? 'text-danger' : '' }}">{{ $eta?->format('Y-m-d H:i') ?? '—' }}</b></div>
                @if($eta && $jc->promised_at && $eta->gt($jc->promised_at))<div class="text-danger mb-1"><i class="bi bi-exclamation-triangle"></i> {{ __('Later than the promised delivery time.') }}</div>@endif
                @can('workshop_planning.update')
                    @if($jc->tasks->where('approval_status', 'approved')->whereIn('status', ['pending', 'paused'])->whereNull('scheduled_start')->isNotEmpty())
                        <form method="post" action="{{ route('workshop.planner.auto', $jc) }}" class="d-grid mb-2">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-magic"></i> {{ __('Schedule technicians automatically') }}</button></form>
                    @elseif($jc->tasks->whereIn('status', ['pending', 'paused'])->whereNotNull('scheduled_start')->isNotEmpty())
                        <form method="post" action="{{ route('workshop.planner.auto', $jc) }}" class="d-grid mb-2">@csrf<input type="hidden" name="replan" value="1"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat"></i> {{ __('Re-plan remaining tasks') }}</button></form>
                    @endif
                @endcan
                @endif
                @if($modPlan && $modTrack)<hr class="my-2">@endif
                @if($modTrack)
                <div class="d-flex mb-1"><span class="text-body-secondary"><i class="bi bi-geo-alt"></i> {{ __('Location') }}</span><span class="ms-auto">{{ $jc->yardLocation?->displayName() ?? '—' }}</span></div>
                <div class="d-flex mb-2"><span class="text-body-secondary"><i class="bi bi-key"></i> {{ __('Key') }}</span><span class="ms-auto">@if($jc->keyHolder)<span class="text-warning">{{ $jc->keyHolder->name }}</span>@elseif($jc->key_hook){{ __('Hook') }} {{ $jc->key_hook }}@else — @endif</span></div>
                <div class="d-flex flex-wrap gap-1">
                    @can('vehicle_tracking.view')<a href="{{ route('tracking.card', $jc) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-key"></i> {{ __('Key & location') }}</a>
                    <a href="{{ route('tracking.key-tag', $jc) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bi bi-qr-code"></i> {{ __('Key tag') }}</a>@endcan
                    @can('test_drives.view')<a href="{{ route('tracking.card', $jc) }}#test-drive" class="btn btn-sm btn-outline-secondary"><i class="bi bi-signpost-2"></i> {{ __('Test drive') }}</a>@endcan
                </div>
                @endif
            </div>
        </div>
        @endif
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i>{{ __('Job details') }}</div>
            <form method="post" action="{{ route('job-cards.update', $jc) }}" class="card-body row g-2">@csrf @method('put')
                <div class="col-6"><label class="form-label small">{{ __('Priority') }}</label><select name="priority" class="form-select form-select-sm" @disabled($closed)>@foreach(\App\Support\Options::PRIORITIES as $p)<option value="{{ $p }}" @selected($jc->priority === $p)>{{ status_label($p) }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label small">{{ __('Department') }}</label><select name="department_id" class="form-select form-select-sm" @disabled($closed)><option value="">—</option>@foreach($departments as $id => $n)<option value="{{ $id }}" @selected($jc->department_id === $id)>{{ $n }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label small">{{ __('Bay') }}</label><select name="bay_id" class="form-select form-select-sm" @disabled($closed)><option value="">—</option>@foreach($bays as $id => $n)<option value="{{ $id }}" @selected($jc->bay_id === $id)>{{ $n }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label small">{{ __('Service advisor') }}</label><select name="service_advisor_id" class="form-select form-select-sm" @disabled($closed)>@foreach($users as $id => $n)<option value="{{ $id }}" @selected($jc->service_advisor_id === $id)>{{ $n }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label small">{{ __('Promised delivery') }}</label><input type="datetime-local" name="promised_at" class="form-control form-control-sm" value="{{ $jc->promised_at?->format('Y-m-d\TH:i') }}" @disabled($closed)></div>
                <div class="col-6"><label class="form-label small">{{ __('Odometer out') }}</label><input type="number" name="odometer_out" class="form-control form-control-sm" value="{{ $jc->odometer_out }}" min="{{ $jc->odometer_in }}" @disabled($closed)></div>
                <div class="col-12"><label class="form-label small">{{ __('Customer complaint') }}</label><textarea name="customer_complaint" rows="2" class="form-control form-control-sm" @disabled($closed)>{{ $jc->customer_complaint }}</textarea></div>
                <div class="col-12"><label class="form-label small">{{ __('Diagnosis') }}</label><textarea name="diagnosis" rows="2" class="form-control form-control-sm" @disabled($closed)>{{ $jc->diagnosis }}</textarea></div>
                <div class="col-12"><label class="form-label small">{{ __('Internal notes') }}</label><textarea name="internal_notes" rows="2" class="form-control form-control-sm" @disabled($closed)>{{ $jc->internal_notes }}</textarea></div>
                @unless($closed)<div class="col-12"><button class="btn btn-sm btn-outline-primary w-100">{{ __('Save') }}</button></div>@endunless
            </form>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-vcard me-1"></i>{{ __('Customer & vehicle') }}</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between"><a href="{{ route('customers.show', $jc->customer) }}" class="fw-semibold">{{ $jc->customer->name }}</a><span dir="ltr">{{ $jc->customer->phone }}</span></div>
                <div class="text-body-secondary mb-2">{{ __('Balance') }}: {{ money($jc->customer->balance()) }}</div>
                <div><a href="{{ route('vehicles.show', $jc->vehicle) }}">{{ $jc->vehicle->label() }}</a></div>
                <div class="text-body-secondary">VIN: {{ $jc->vehicle->vin ?? '—' }} · {{ __('Odometer in') }}: {{ number_format($jc->odometer_in) }} km · {{ __('Fuel') }}: {{ $jc->fuel_level }}%</div>
                @if($jc->fleet)<div class="mt-1"><span class="badge text-bg-info"><i class="bi bi-truck"></i> {{ $jc->fleet->name }}</span></div>@endif
                @if($jc->insuranceClaim)<div class="mt-1"><a href="{{ route('insurance.claims.show', $jc->insuranceClaim) }}" class="badge text-bg-primary text-decoration-none"><i class="bi bi-shield"></i> {{ $jc->insuranceClaim->number }}</a></div>@endif
                @if($jc->checkIn)
                    <hr class="my-2">
                    <div>{{ __('Received by') }} {{ $jc->checkIn->receiver?->name }} · {{ $jc->checkIn->received_at->format('Y-m-d H:i') }}</div>
                    @if($jc->checkIn->existing_damages)<div>{{ __('Damages') }}: {{ collect($jc->checkIn->existing_damages)->map(fn($d) => status_label($d['type'] ?? '').' '.($d['description'] ?? ''))->implode('، ') }}</div>@endif
                    @if($jc->checkIn->personal_items)<div>{{ __('Belongings') }}: {{ implode('، ', $jc->checkIn->personal_items) }}</div>@endif
                @endif
            </div>
        </div>

        @if(in_array($jc->status, ['in_progress', 'quality_inspection']) && auth()->user()->can('quality_checks.create'))
        <div class="card mb-3 border-primary" id="qc-card">
            <div class="card-header"><i class="bi bi-patch-check me-1"></i>{{ __('Quality inspection') }}</div>
            <form method="post" action="{{ route('job-cards.qc', $jc) }}" class="card-body">@csrf <x-idem/>
                @php
                    $qcTpl = \App\Modules\Workshop\Models\InspectionTemplate::query()->with('items')->where('is_active', true)->where('kind', 'qc')
                        ->where(fn ($q) => $q->where('vehicle_type', $jc->vehicle->vehicle_type)->orWhereNull('vehicle_type'))->orderByRaw('vehicle_type IS NULL')->first();
                    $qcItems = $qcTpl ? $qcTpl->items->sortBy('sort')->mapWithKeys(fn ($i) => ['i'.$i->id => app()->getLocale() === 'en' && $i->label_en ? $i->label_en : $i->label])->all()
                        : ['road_test' => __('Road test done'), 'no_leaks' => __('No leaks'), 'warning_lights' => __('No warning lights'), 'cleanliness' => __('Vehicle clean'), 'old_parts' => __('Old parts returned/disposed')];
                @endphp
                @if($qcTpl)<div class="small text-body-secondary mb-1">{{ $qcTpl->name }}</div>@endif
                @foreach($qcItems as $k => $l)
                    <div class="form-check small"><input class="form-check-input" type="checkbox" name="checklist[{{ $k }}]" value="1" id="qc_{{ $k }}"><label class="form-check-label" for="qc_{{ $k }}">{{ $l }}</label></div>
                @endforeach
                <textarea name="notes" class="form-control form-control-sm my-2" rows="2" placeholder="{{ __('Notes') }}"></textarea>
                <div class="d-flex gap-2">
                    <button name="result" value="pass" class="btn btn-sm btn-success flex-fill"><i class="bi bi-check2"></i> {{ __('Pass') }}</button>
                    <button name="result" value="fail" class="btn btn-sm btn-danger flex-fill"><i class="bi bi-x"></i> {{ __('Fail / rework') }}</button>
                </div>
            </form>
        </div>
        @endif
        @if($jc->qualityChecks->isNotEmpty())
            <div class="card mb-3"><div class="card-header">{{ __('Quality checks') }}</div><ul class="list-group list-group-flush small">
                @foreach($jc->qualityChecks as $qc)<li class="list-group-item">{!! status_badge($qc->result) !!} {{ $qc->inspector?->name }} · {{ $qc->checked_at->format('Y-m-d H:i') }} <div class="text-body-secondary">{{ $qc->notes }}</div></li>@endforeach
            </ul></div>
        @endif

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-receipt me-1"></i>{{ __('Invoices') }}</div>
            <div class="list-group list-group-flush">
                @forelse($jc->invoices as $inv)
                    <a href="{{ route('invoices.show', $inv) }}" class="list-group-item list-group-item-action d-flex"><span>{{ $inv->displayNumber() }}</span><span class="ms-auto font-monospace me-2">{{ money($inv->total, false) }}</span>{!! status_badge($inv->status) !!}</a>
                @empty
                    <div class="card-body small text-body-secondary">{{ __('Not invoiced yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@can('job_cards.approve_additional')
<div class="modal fade" id="additionalModal" tabindex="-1"><div class="modal-dialog modal-lg"><form method="post" action="{{ route('job-cards.additional', $jc) }}" class="modal-content">@csrf <x-idem/>
    <div class="modal-header"><h5 class="modal-title">{{ __('Customer decision on additional work') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <table class="table table-sm">
            @foreach($pendingTasks as $t)<tr><td>{{ $t->description }}</td><td class="font-monospace">{{ money($t->line_total, false) }}</td><td><select name="tasks[{{ $t->id }}]" class="form-select form-select-sm"><option value="approved">{{ __('Approved') }}</option><option value="rejected">{{ __('Rejected') }}</option></select></td></tr>@endforeach
            @foreach($pendingParts as $p)<tr><td>{{ $p->description }} × {{ qty($p->qty) }}</td><td class="font-monospace">{{ money((float) $p->qty * (float) $p->unit_price, false) }}</td><td><select name="parts[{{ $p->id }}]" class="form-select form-select-sm"><option value="approved">{{ __('Approved') }}</option><option value="rejected">{{ __('Rejected') }}</option></select></td></tr>@endforeach
        </table>
        <div class="row g-2">
            <div class="col-md-6"><label class="form-label">{{ __('Approved by (customer name)') }}</label><input name="approved_by" class="form-control" value="{{ $jc->customer->name }}" required></div>
            <div class="col-md-6"><label class="form-label">{{ __('Channel') }}</label><select name="channel" class="form-select"><option value="phone">{{ __('Phone') }}</option><option value="in_person">{{ __('In person') }}</option><option value="whatsapp">WhatsApp</option><option value="email">{{ __('Email') }}</option></select></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">{{ __('Save decision') }}</button></div>
</form></div></div>
@endcan
@endsection
