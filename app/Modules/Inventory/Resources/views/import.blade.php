@extends('layouts.app')
@section('title', __('Import parts'))
@section('content')
<x-page-header :title="__('Import parts from CSV')" icon="bi-upload" :breadcrumbs="[__('Spare Parts Catalog') => route('crud.index', 'parts'), __('Import')]"/>
<div class="card" style="max-width:760px">
    <form method="post" enctype="multipart/form-data" class="card-body">@csrf <x-idem/>
        <p>{{ __('CSV columns (first row = header):') }}</p>
        <pre class="bg-body-tertiary p-2 rounded small" dir="ltr">sku,name_ar,name_en,oem_number,barcode,brand,category,part_type,purchase_price,sale_price,min_stock</pre>
        <p class="small text-body-secondary">{{ __('Existing SKUs are updated; new SKUs are created. part_type: genuine, oem, aftermarket, used, refurbished, damaged, scrap. Stock quantities are entered through an opening stock adjustment.') }}</p>
        <input type="file" name="file" accept=".csv,text/csv" class="form-control mb-3" required>
        <button class="btn btn-primary"><i class="bi bi-upload"></i> {{ __('Import') }}</button>
    </form>
</div>
@endsection
