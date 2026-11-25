<div class="card mb-3">
    <div class="card-header">{{ __('Prices by vehicle type') }}</div>
    <div class="card-body">
        @if($service->vehiclePrices->isEmpty())
            <span class="text-body-secondary small">{{ __('Same price for all vehicle types.') }}</span>
        @else
            <div class="d-flex flex-wrap gap-2">@foreach($service->vehiclePrices as $vp)<span class="badge text-bg-light border p-2">{{ status_label($vp->vehicle_type) }}: <b>{{ money($vp->price) }}</b></span>@endforeach</div>
        @endif
    </div>
</div>
