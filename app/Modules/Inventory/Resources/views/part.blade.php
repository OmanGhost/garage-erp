@extends('layouts.app')
@section('title', $p->label())
@section('content')
<x-page-header :title="$p->displayName()" icon="bi-gear-wide-connected" :breadcrumbs="[__('Spare Parts Catalog') => route('crud.index', 'parts'), $p->sku]"
    :subtitle="'SKU '.e($p->sku).' · OEM '.e($p->oem_number ?? '—').' · '.status_label($p->part_type).($p->condition_grade ? ' · Grade '.e($p->condition_grade) : '')">
    @can('parts.update')<a href="{{ route('crud.edit', ['parts', $p->id]) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>@endcan
    <a href="{{ route('parts.labels', ['ids' => [$p->id], 'copies' => 1]) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-upc"></i> {{ __('Print label') }}</a>
</x-page-header>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="row g-3 mb-3">
            @foreach([[__('On hand'), qty($p->onHand())], [__('Available'), qty($p->available())], [__('Average cost'), money($p->average_cost)], [__('Retail price'), money($p->sale_price)], [__('Sold (qty)'), qty($sales->qty)], [__('Forecast next month'), qty($forecast)]] as [$l, $v])
                <div class="col-6 col-md-4"><div class="card kpi"><div class="card-body py-2"><div class="kpi-label">{{ $l }}</div><div class="kpi-value fs-5">{{ $v }}</div></div></div></div>
            @endforeach
        </div>
        <div class="card mb-3"><div class="card-header">{{ __('Stock by warehouse') }}</div><table class="table table-sm table-erp mb-0">
            <thead><tr><th>{{ __('Warehouse') }}</th><th>{{ __('Location') }}</th><th>{{ __('On hand') }}</th><th>{{ __('Reserved') }}</th><th>{{ __('Available') }}</th><th class="text-end">{{ __('Avg. cost') }}</th></tr></thead>
            <tbody>@forelse($p->stockLevels as $s)<tr><td>{{ $s->warehouse?->displayName() }}</td><td>{{ $s->location?->code }}</td><td>{{ qty($s->qty_on_hand) }}</td><td>{{ qty($s->qty_reserved) }}</td><td class="fw-bold">{{ qty($s->available()) }}</td><td class="text-end font-monospace">{{ money($s->avg_cost, false) }}</td></tr>
            @empty<tr><td colspan="6" class="text-body-secondary">{{ __('No stock.') }}</td></tr>@endforelse</tbody>
        </table></div>
        @if($batches->isNotEmpty())
        <div class="card mb-3"><div class="card-header">{{ __('Batches, serial numbers & cost layers (FIFO)') }}</div><div class="table-responsive"><table class="table table-sm table-erp mb-0">
            <thead><tr><th>{{ __('Received') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Batch') }}</th><th>{{ __('Serial') }}</th><th>{{ __('Expiry') }}</th><th>{{ __('Remaining') }}</th><th class="text-end">{{ __('Unit cost') }}</th></tr></thead>
            <tbody>@foreach($batches as $b)<tr><td>{{ $b->received_at->format('Y-m-d') }}</td><td>{{ $b->warehouse?->displayName() }}</td><td>{{ $b->batch_number }}</td><td>{{ $b->serial_number }}</td><td class="{{ $b->expiry_date?->isPast() ? 'text-danger' : '' }}">{{ $b->expiry_date?->format('Y-m-d') }}</td><td>{{ qty($b->qty_remaining) }}</td><td class="text-end font-monospace">{{ money($b->unit_cost, false) }}</td></tr>@endforeach</tbody>
        </table></div></div>
        @endif
        <div class="card mb-3"><div class="card-header d-flex">{{ __('Recent movements') }}<a href="{{ route('crud.index', ['stock-movements', 'q' => $p->sku]) }}" class="ms-auto small">{{ __('All') }}</a></div><div class="table-responsive"><table class="table table-sm table-erp mb-0">
            <tbody>@forelse($movements as $m)<tr><td>{{ $m->moved_at->format('Y-m-d H:i') }}</td><td>{{ status_label($m->type) }}</td><td>{{ $m->warehouse?->displayName() }}</td><td class="{{ (float) $m->qty > 0 ? 'text-success' : 'text-danger' }}">{{ (float) $m->qty > 0 ? '+' : '' }}{{ qty($m->qty) }}</td><td>{{ qty($m->balance_after) }}</td><td>{{ $m->reference_number }}</td><td class="small">{{ $m->user?->name }}</td></tr>
            @empty<tr><td class="text-body-secondary">—</td></tr>@endforelse</tbody>
        </table></div></div>
    </div>
    <div class="col-xl-4">
        @if($p->imageUrl())<div class="card mb-3"><img src="{{ $p->imageUrl() }}" class="card-img-top" alt=""></div>@endif
        <div class="card mb-3"><div class="card-body text-center">
            <img src="{{ \App\Support\Codes::barcode($p->barcode ?: $p->sku) }}" style="max-width:100%;height:50px"><div class="small font-monospace">{{ $p->barcode ?: $p->sku }}</div>
            <img src="{{ \App\Support\Codes::qr($p->sku, 3) }}" style="width:90px" class="mt-2">
        </div></div>
        <div class="card mb-3"><div class="card-body small">
            <div><b>{{ __('Brand') }}:</b> {{ $p->brand?->name }} · {{ $p->country_of_origin }}</div>
            <div><b>{{ __('Category') }}:</b> {{ $p->category?->displayName() }}</div>
            <div><b>{{ __('Prices') }}:</b> {{ __('Retail') }} {{ money($p->sale_price, false) }} · {{ __('Wholesale') }} {{ money($p->wholesale_price, false) }} · {{ __('Workshop') }} {{ money($p->workshop_price, false) }} · {{ __('Min') }} {{ money($p->min_sale_price, false) }}</div>
            <div><b>{{ __('Warranty') }}:</b> {{ $p->warranty_days }} {{ __('days') }} / {{ number_format($p->warranty_km) }} km</div>
            <div><b>{{ __('Default supplier') }}:</b> {{ $p->supplier?->name ?? '—' }}</div>
            <div><b>{{ __('Alternative numbers') }}:</b> {{ $p->alternatives->pluck('number')->implode(', ') ?: '—' }}</div>
            <div><b>{{ __('Sales') }}:</b> {{ money($sales->revenue) }} · {{ __('Profit') }} {{ money((float) $sales->revenue - (float) $sales->cost) }}</div>
            @if($p->dismantled_vehicle_id)<div><b>{{ __('Source') }}:</b> <a href="{{ route('dismantling.show', $p->dismantled_vehicle_id) }}">{{ __('Dismantled vehicle') }} #{{ $p->dismantled_vehicle_id }}</a></div>@endif
        </div></div>
        <div class="card mb-3"><div class="card-header">{{ __('Compatible vehicles') }}</div>
            <ul class="list-group list-group-flush small">
                @forelse($p->compatibilities as $c)
                    <li class="list-group-item d-flex">{{ $c->make?->name }} {{ $c->vehicleModel?->name }} {{ $c->year_from }}{{ $c->year_to ? '–'.$c->year_to : '' }} {{ $c->engine }} @if($c->vin_pattern)<code>{{ $c->vin_pattern }}*</code>@endif
                        @can('parts.update')<form method="post" action="{{ route('parts.compat.destroy', $c) }}" class="ms-auto">@csrf @method('delete')<button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x"></i></button></form>@endcan</li>
                @empty<li class="list-group-item text-body-secondary">{{ __('Universal / not specified') }}</li>@endforelse
            </ul>
            @can('parts.update')
            <form method="post" action="{{ route('parts.compat.store', $p) }}" class="card-body border-top row g-1">@csrf
                <div class="col-6"><select name="vehicle_make_id" id="f_vehicle_make_id" class="form-select form-select-sm"><option value="">{{ __('Make') }}</option>@foreach($makes as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
                <div class="col-6"><x-lookup name="vehicle_model_id" type="vehicle_models" size="sm" params="make_id=#f_vehicle_make_id" :placeholder="__('Model')"/></div>
                <div class="col-3"><input name="year_from" type="number" class="form-control form-control-sm" placeholder="{{ __('From') }}"></div>
                <div class="col-3"><input name="year_to" type="number" class="form-control form-control-sm" placeholder="{{ __('To') }}"></div>
                <div class="col-6"><input name="vin_pattern" class="form-control form-control-sm" placeholder="{{ __('VIN prefix (WMI+VDS)') }}"></div>
                <div class="col-9"><input name="engine" class="form-control form-control-sm" placeholder="{{ __('Engine') }}"></div>
                <div class="col-3"><button class="btn btn-sm btn-primary w-100">+</button></div>
            </form>
            @endcan
        </div>
        <x-attachments :model="$p" :title="__('Part photos')"/>
    </div>
</div>
@endsection
