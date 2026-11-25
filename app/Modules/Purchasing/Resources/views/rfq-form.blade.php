@extends('layouts.app')
@section('title', __('New RFQ'))
@section('content')
<x-page-header :title="__('New request for quotation')" icon="bi-envelope-paper" :breadcrumbs="[__('RFQs') => route('crud.index', 'rfqs'), __('New')]"/>
<form method="post" action="{{ route('rfqs.store') }}" class="card">@csrf <x-idem/>
    @if($pr)<input type="hidden" name="purchase_request_id" value="{{ $pr->id }}">@endif
    <div class="card-body row g-3">
        <div class="col-md-8"><label class="form-label required">{{ __('Suppliers to invite') }}</label>
            <select name="supplier_ids[]" class="form-select" multiple size="6" required>@foreach($suppliers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
            <div class="form-text">{{ __('Hold Ctrl to select several suppliers.') }}</div></div>
        <div class="col-md-4"><label class="form-label">{{ __('Reply due date') }}</label><input type="date" name="due_date" class="form-control" value="{{ now()->addDays(3)->toDateString() }}"></div>
        <div class="col-12">
            @if($pr)
                <div class="alert alert-light">{{ __('Items are taken from purchase request') }} {{ $pr->number }}: {{ $pr->items->map(fn($i) => $i->description.' × '.qty($i->qty))->implode('، ') }}</div>
                <input type="hidden" name="items[0][qty]" value="0">
            @else
                <x-line-items lookup="parts" :show-tax="false" :show-discount="false" price-field="purchase_price" :cost-label="__('Target price')"/>
            @endif
        </div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">{{ __('Create RFQ') }}</button></div>
</form>
@endsection
