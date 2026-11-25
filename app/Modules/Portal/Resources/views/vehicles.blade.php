@extends('layouts.portal')
@section('title', __('My vehicles'))
@section('content')
<h5 class="mb-3">{{ __('My vehicles') }}</h5>
@foreach($vehicles as $v)
    <div class="card mb-2"><div class="card-body d-flex align-items-center gap-2"><span class="plate-badge">{{ $v->plate() }}</span><div><div>{{ $v->title() }}</div><div class="small text-body-secondary">{{ __('Next service') }}: {{ $v->next_service_date?->format('Y-m-d') ?? '—' }} {{ $v->next_service_odometer ? '/ '.number_format($v->next_service_odometer).' km' : '' }}</div></div></div></div>
@endforeach
<form method="post" action="{{ route('portal.vehicles.store') }}" class="card mt-3">@csrf<div class="card-header">{{ __('Add a vehicle') }}</div><div class="card-body row g-2">
    <div class="col-6"><input name="plate_number" class="form-control" placeholder="{{ __('Plate number') }}" required></div>
    <div class="col-6"><input name="plate_code" class="form-control" placeholder="{{ __('Code') }}"></div>
    <div class="col-6"><select name="vehicle_make_id" class="form-select"><option value="">{{ __('Make') }}</option>@foreach($makes as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
    <div class="col-6"><input type="number" name="year" class="form-control" placeholder="{{ __('Year') }}"></div>
    <div class="col-8"><input name="vin" class="form-control" placeholder="VIN" maxlength="17"></div>
    <div class="col-4"><input name="color" class="form-control" placeholder="{{ __('Color') }}"></div>
    <div class="col-12"><button class="btn btn-primary w-100">{{ __('Add') }}</button></div>
</div></form>
@endsection
