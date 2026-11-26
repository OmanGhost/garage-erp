@extends('layouts.app')
@section('title', $v->number)
@section('content')
<x-page-header :title="__('Dismantling file').' '.$v->number" icon="bi-car-front-fill" :breadcrumbs="[__('Dismantled Vehicles') => route('crud.index', 'dismantled-vehicles'), $v->number]"
    :subtitle="e($v->title()).' · VIN '.e($v->vin ?? '—').' · '.status_badge($v->status)">
    <a href="{{ route('dismantling.labels', $v) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-upc"></i> {{ __('Print labels') }}</a>
    @if($v->status !== 'closed')
        @can('dismantling.approve')<form method="post" action="{{ route('dismantling.close', $v) }}" data-confirm="{{ __('Close the file? Unallocated cost will be written off.') }}">@csrf <x-idem/><button class="btn btn-outline-danger">{{ __('Close file') }}</button></form>@endcan
    @endif
</x-page-header>
<div class="row g-3 mb-3">
    @foreach([[__('Total cost'), $profit['total_cost']], [__('Allocated to parts'), $profit['allocated_cost']], [__('Parts revenue'), $profit['parts_revenue']], [__('Scrap revenue'), $profit['scrap_revenue']], [__('Remaining stock value'), $profit['stock_value']], [__('Profit'), $profit['profit']]] as [$l, $val])
        <div class="col-6 col-md-4 col-xl-2"><div class="card kpi"><div class="card-body py-2"><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-5 {{ $l === __('Profit') ? ((float) $val >= 0 ? 'text-success' : 'text-danger') : '' }}">{{ money($val, false) }}</div></div></div></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header d-flex"><span>{{ __('Extracted parts') }} ({{ $profit['parts_sold'] }}/{{ $profit['parts_total'] }} {{ __('sold') }})</span></div>
            <div class="table-responsive"><table class="table table-erp mb-0 align-middle">
                <thead><tr><th>{{ __('Serial') }}</th><th>{{ __('Part') }}</th><th>{{ __('Grade') }}</th><th class="text-end">{{ __('Est. value') }}</th><th class="text-end">{{ __('Allocated cost') }}</th><th class="text-end">{{ __('Sale price') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>@forelse($v->parts as $p)
                    <tr><td class="font-monospace small">{{ $p->serial_number }}</td><td>@if($p->part)<a href="{{ route('parts.show', $p->part) }}">{{ $p->name }}</a>@else{{ $p->name }}@endif<div class="small text-body-secondary">{{ $p->oem_number }}</div></td>
                        <td><span class="badge text-bg-{{ ['A' => 'success', 'B' => 'info', 'C' => 'warning', 'damaged' => 'danger'][$p->grade] }}">{{ $p->grade }}</span></td>
                        <td class="text-end font-monospace">{{ money($p->estimated_value, false) }}</td><td class="text-end font-monospace">{{ money($p->allocated_cost, false) }}</td><td class="text-end font-monospace">{{ money($p->sale_price, false) }}</td>
                        <td class="small">{{ $p->warehouse?->displayName() }}</td><td>{!! status_badge($p->status) !!}</td>
                        <td>@if($p->status === 'pending')<form method="post" action="{{ route('dismantling.parts.destroy', $p) }}">@csrf @method('delete')<button class="btn btn-sm btn-link text-danger"><i class="bi bi-x"></i></button></form>@endif</td></tr>
                @empty<tr><td colspan="9"><div class="empty-state"><i class="bi bi-gear"></i>{{ __('Register the parts extracted from this vehicle.') }}</div></td></tr>@endforelse</tbody>
            </table></div>
            @if($v->status !== 'closed' && $v->status !== 'completed')
            @can('dismantling.update')
            <form method="post" action="{{ route('dismantling.parts.store', $v) }}" enctype="multipart/form-data" class="card-body border-top row g-2 align-items-end">@csrf <x-idem/>
                <div class="col-md-3"><label class="form-label small">{{ __('Part name') }}</label><input name="name" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><label class="form-label small">{{ __('OEM no.') }}</label><input name="oem_number" class="form-control form-control-sm"></div>
                <div class="col-md-1"><label class="form-label small">{{ __('Grade') }}</label><select name="grade" class="form-select form-select-sm"><option>A</option><option>B</option><option>C</option><option value="damaged">{{ __('Damaged') }}</option></select></div>
                <div class="col-md-2"><label class="form-label small">{{ __('Est. value') }}</label><input name="estimated_value" type="number" step="0.001" min="0" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><label class="form-label small">{{ __('Sale price') }}</label><input name="sale_price" type="number" step="0.001" min="0" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label class="form-label small">{{ __('Warehouse') }}</label><select name="warehouse_id" class="form-select form-select-sm">@foreach($warehouses as $id => $n)<option value="{{ $id }}" @selected($v->warehouse_id === $id)>{{ $n }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label small">{{ __('Link to existing catalog part (optional)') }}</label><x-lookup name="part_id" type="parts" size="sm"/></div>
                <div class="col-md-4"><label class="form-label small">{{ __('Photos') }}</label><input type="file" name="photos[]" multiple accept="image/*" capture="environment" class="form-control form-control-sm"></div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus"></i> {{ __('Add') }}</button></div>
            </form>
            @if($v->parts->where('status', 'pending')->isNotEmpty())
                @can('dismantling.approve')
                <form method="post" action="{{ route('dismantling.stock', $v) }}" class="card-footer d-flex gap-2 align-items-center">@csrf <x-idem/>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="final" value="1" id="fin"><label class="form-check-label small" for="fin">{{ __('Final batch (allocate all remaining cost)') }}</label></div>
                    <button class="btn btn-success ms-auto"><i class="bi bi-box-arrow-in-down"></i> {{ __('Allocate cost & move pending parts to stock') }}</button>
                </form>
                @endcan
            @endif
            @endcan
            @endif
        </div>
        @if($v->status !== 'closed' && $v->status !== 'completed')
        @can('dismantling.update')
        <details class="card mb-3" id="parts-checklist" @if($v->parts->isEmpty()) open @endif>
            <summary class="card-header d-flex align-items-center" style="cursor:pointer"><i class="bi bi-ui-checks-grid me-2 text-brand"></i><span class="fw-semibold">{{ __('Vehicle parts checklist') }}</span><span class="small text-body-secondary ms-2 d-none d-md-inline">{{ __('Tick several parts at once with their factory numbers') }}</span><i class="bi bi-chevron-down ms-auto"></i></summary>
            <form method="post" action="{{ route('dismantling.parts.checklist', $v) }}" class="card-body">@csrf <x-idem/>
                <div class="row">@include('dismantling::_parts-checklist', ['vehicle' => $v])</div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-4"><label class="form-label small mb-1" for="cl_wh">{{ __('Warehouse') }}</label><select id="cl_wh" name="warehouse_id" class="form-select form-select-sm">@foreach($warehouses as $id => $n)<option value="{{ $id }}" @selected($v->warehouse_id === $id)>{{ $n }}</option>@endforeach</select></div>
                    <div class="col-md-4 ms-auto d-grid"><button class="btn btn-primary"><i class="bi bi-check2-all"></i> {{ __('Register ticked parts') }}</button></div>
                </div>
            </form>
        </details>
        @endcan
        @endif
        <x-attachments :model="$v" :title="__('Vehicle photos & documents')"/>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-body small">
            <div><b>{{ __('Source') }}:</b> {{ status_label($v->source) }} · {{ $v->seller_name }} {{ $v->seller_phone }}</div>
            <div><b>{{ __('Supplier') }}:</b> {{ $v->supplier?->name ?? '—' }}</div>
            <div><b>{{ __('Purchase date') }}:</b> {{ $v->purchase_date->format('Y-m-d') }}</div>
            <div><b>{{ __('Purchase / transport / dismantling / other') }}:</b><br>{{ money($v->purchase_cost, false) }} / {{ money($v->transport_cost, false) }} / {{ money($v->dismantling_cost, false) }} / {{ money($v->other_costs, false) }}</div>
            <div><b>{{ __('Mileage') }}:</b> {{ number_format($v->mileage) }} km · {{ $v->color }}</div>
        </div></div>
        @if($v->status !== 'closed')
        @can('dismantling.update')
        <div class="card mb-3"><div class="card-header">{{ __('Add cost') }}</div>
            <form method="post" action="{{ route('dismantling.cost', $v) }}" class="card-body row g-2">@csrf <x-idem/>
                <div class="col-6"><select name="type" class="form-select form-select-sm"><option value="transport_cost">{{ __('Transport') }}</option><option value="dismantling_cost">{{ __('Dismantling labour') }}</option><option value="other_costs">{{ __('Other') }}</option></select></div>
                <div class="col-6"><input name="amount" type="number" step="0.001" min="0.001" class="form-control form-control-sm" placeholder="{{ __('Amount') }}" required></div>
                <div class="col-8"><select name="bank_account_id" class="form-select form-select-sm"><option value="">{{ __('On supplier account') }}</option>@foreach($banks as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
                <div class="col-4"><button class="btn btn-sm btn-outline-primary w-100">{{ __('Add') }}</button></div>
            </form>
        </div>
        <div class="card mb-3"><div class="card-header">{{ __('Scrap & metal sale') }}</div>
            <form method="post" action="{{ route('dismantling.scrap', $v) }}" class="card-body row g-2">@csrf <x-idem/>
                <div class="col-12"><input name="description" class="form-control form-control-sm" placeholder="{{ __('Description') }}" required></div>
                <div class="col-6"><input name="weight_kg" type="number" step="0.001" min="0" class="form-control form-control-sm" placeholder="kg"></div>
                <div class="col-6"><input name="amount" type="number" step="0.001" min="0" class="form-control form-control-sm" placeholder="{{ __('Amount') }}" required></div>
                <div class="col-8"><select name="bank_account_id" class="form-select form-select-sm"><option value="">—</option>@foreach($banks as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
                <div class="col-4"><button class="btn btn-sm btn-outline-primary w-100">{{ __('Record') }}</button></div>
            </form>
            <ul class="list-group list-group-flush small">@foreach($v->scraps as $s)<li class="list-group-item d-flex">{{ $s->date->format('Y-m-d') }} {{ $s->description }} {{ $s->weight_kg ? qty($s->weight_kg).'kg' : '' }}<span class="ms-auto font-monospace">{{ money($s->amount, false) }}</span></li>@endforeach</ul>
        </div>
        @endcan
        @endif
    </div>
</div>
@endsection
