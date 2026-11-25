@props(['rows' => [], 'lookup' => 'parts', 'priceField' => 'sale_price', 'tax' => null, 'services' => false, 'showTax' => true, 'showDiscount' => true, 'name' => 'items', 'costLabel' => null, 'withOptions' => false, 'vehicleType' => null])
@php $tax = $tax ?? (string) (tenant()->company()?->vat_rate ?? 5); @endphp
<div x-data="lineItems({ rows: @js($rows), lookup: @js($lookup), priceField: @js($priceField), tax: {{ (float) $tax }}, allowServices: @js($services) })">
    <div class="table-responsive">
        <table class="table table-sm align-middle line-items">
            <thead class="table-light">
            <tr>
                @if($services)<th style="width:110px">{{ __('Type') }}</th>@endif
                <th style="min-width:260px">{{ __('Item') }}</th>
                <th style="min-width:180px">{{ __('Description') }}</th>
                @if($withOptions)<th style="width:80px">{{ __('Option') }}</th>@endif
                <th style="width:95px">{{ __('Qty') }}</th>
                <th style="width:120px">{{ $costLabel ?? __('Unit price') }}</th>
                @if($showDiscount)<th style="width:105px">{{ __('Discount') }}</th>@endif
                @if($showTax)<th style="width:80px">{{ __('VAT %') }}</th>@endif
                <th class="text-end" style="width:110px">{{ __('Total') }}</th>
                <th style="width:40px"></th>
            </tr>
            </thead>
            <tbody>
            <template x-for="(r, i) in rows" :key="r.uid">
                <tr>
                    @if($services)
                        <td>
                            <select class="form-select form-select-sm" x-model="r.item_type" :name="`{{ $name }}[${i}][item_type]`" @change="r.part_id='';r.service_id='';r.label=''">
                                <option value="service">{{ __('Service') }}</option>
                                <option value="part">{{ __('Part') }}</option>
                                <option value="labor">{{ __('Labor') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                        </td>
                    @else
                        <input type="hidden" :name="`{{ $name }}[${i}][item_type]`" :value="r.item_type">
                    @endif
                    <td>
                        <template x-if="r.item_type === 'part' || r.item_type === 'service'">
                            <div class="lookup" :data-lookup="r.item_type === 'part' ? 'parts' : 'services'" data-params="{{ $vehicleType ? 'vehicle_type='.$vehicleType : '' }}" @lookup-selected="picked(i, $event)">
                                <input type="hidden" :value="r.item_type === 'part' ? r.part_id : r.service_id">
                                <input type="text" class="form-control form-control-sm" :value="r.label" placeholder="{{ __('Search by name / SKU / barcode...') }}" autocomplete="off">
                                <div class="lookup-menu"></div>
                            </div>
                        </template>
                        <template x-if="r.item_type !== 'part' && r.item_type !== 'service'"><span class="small text-body-secondary">{{ __('Free text line') }}</span></template>
                        <input type="hidden" :name="`{{ $name }}[${i}][part_id]`" :value="r.part_id">
                        <input type="hidden" :name="`{{ $name }}[${i}][service_id]`" :value="r.service_id">
                        <template x-if="r.id"><input type="hidden" :name="`{{ $name }}[${i}][id]`" :value="r.id"></template>
                    </td>
                    <td><input class="form-control form-control-sm" x-model="r.description" :name="`{{ $name }}[${i}][description]`" maxlength="250"></td>
                    @if($withOptions)<td><input class="form-control form-control-sm" x-model="r.option_group" :name="`{{ $name }}[${i}][option_group]`" placeholder="A/B" maxlength="10"></td>@endif
                    <td><input type="number" step="0.001" min="0.001" class="form-control form-control-sm" x-model="r.qty" :name="`{{ $name }}[${i}][qty]`" required></td>
                    <td><input type="number" step="0.001" min="0" class="form-control form-control-sm" x-model="r.unit_price" :name="`{{ $name }}[${i}][{{ $costLabel ? 'unit_cost' : 'unit_price' }}]`" required></td>
                    @if($showDiscount)<td><input type="number" step="0.001" min="0" class="form-control form-control-sm" x-model="r.discount" :name="`{{ $name }}[${i}][discount]`"></td>@endif
                    @if($showTax)<td><input type="number" step="0.001" min="0" max="100" class="form-control form-control-sm" x-model="r.tax_rate" :name="`{{ $name }}[${i}][tax_rate]`"></td>@endif
                    <td class="text-end font-monospace" x-text="fmt(net(r))"></td>
                    <td><button type="button" class="btn btn-sm btn-link text-danger" @click="remove(i)"><i class="bi bi-x-circle"></i></button></td>
                </tr>
            </template>
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-wrap justify-content-between gap-3">
        <div class="d-flex gap-2 align-items-start">
            <button type="button" class="btn btn-sm btn-outline-primary" @click="add()"><i class="bi bi-plus-lg"></i> {{ __('Add line') }}</button>
            @if($services)<button type="button" class="btn btn-sm btn-outline-secondary" @click="add('part')"><i class="bi bi-gear"></i> {{ __('Add part') }}</button>@endif
        </div>
        <table class="table table-sm totals-box w-auto mb-0">
            <tr><td>{{ __('Subtotal') }}</td><td class="text-end font-monospace" x-text="fmt(subtotal)"></td></tr>
            @if($showDiscount)<tr><td>{{ __('Discount') }}</td><td class="text-end font-monospace text-danger" x-text="'-' + fmt(discount)"></td></tr>@endif
            @if($showTax)<tr><td>{{ __('VAT') }}</td><td class="text-end font-monospace" x-text="fmt(taxTotal)"></td></tr>@endif
            <tr class="row-total"><td>{{ __('Total') }} ({{ tenant()->company()?->currency_code ?? 'OMR' }})</td><td class="text-end font-monospace" x-text="fmt(total)"></td></tr>
        </table>
    </div>
</div>
