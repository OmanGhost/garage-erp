@extends('layouts.app')
@section('title', __('Sales return'))
@section('content')
<x-page-header :title="__('Sales return / credit note for').' '.$inv->number" icon="bi-arrow-return-left" :breadcrumbs="[__('Invoices') => route('crud.index', 'invoices'), $inv->number => route('invoices.show', $inv), __('Return')]"/>
<form method="post" action="{{ route('credit-notes.store', $inv) }}" class="card">@csrf <x-idem/>
    <div class="table-responsive"><table class="table table-erp mb-0 align-middle">
        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Invoiced') }}</th><th>{{ __('Already returned') }}</th><th>{{ __('Return qty') }}</th><th class="text-end">{{ __('Price charged') }}</th><th class="text-end">{{ __('Refund (incl. VAT)') }}</th></tr></thead>
        <tbody>@foreach($inv->items as $it)
            @php
                $max = (float) $it->qty - (float) $it->returned_qty;
                // price actually charged per unit = (qty x list price - line discount) / qty
                $net = (float) $it->qty > 0 ? \App\Support\Money::div(\App\Support\Money::sub(\App\Support\Money::mul($it->qty, $it->unit_price), $it->discount), $it->qty, 3) : $it->unit_price;
                $discounted = \App\Support\Money::isPositive($it->discount);
            @endphp
            <tr><td>{{ $it->description }}</td><td>{{ qty($it->qty) }}</td><td>{{ qty($it->returned_qty) }}</td>
                <td style="width:130px"><input type="number" name="qty[{{ $it->id }}]" step="0.001" min="0" max="{{ $max }}" value="0" class="form-control form-control-sm" data-return-qty data-net="{{ $net }}" data-tax="{{ $it->tax_rate }}" @disabled($max <= 0)></td>
                <td class="text-end font-monospace">@if($discounted)<del class="text-body-tertiary small">{{ money($it->unit_price, false) }}</del><br>@endif{{ money($net, false) }}</td>
                <td class="text-end font-monospace" data-return-line>0.000</td></tr>
        @endforeach</tbody>
        <tfoot><tr class="fw-bold"><td colspan="5" class="text-end">{{ __('Total refund (incl. VAT)') }}</td><td class="text-end font-monospace" data-return-total>0.000</td></tr></tfoot>
    </table></div>
    <div class="card-body pt-0 small text-body-secondary"><i class="bi bi-info-circle"></i> {{ __('Returns are valued at the price charged on the invoice, after discount. The final amount is calculated when saving.') }}</div>
    <div class="card-body row g-2">
        <div class="col-md-6"><label class="form-label required">{{ __('Reason') }}</label><input name="reason" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">{{ __('Return to warehouse') }}</label><select name="warehouse_id" class="form-select">@foreach($warehouses as $id => $n)<option value="{{ $id }}" @selected($inv->warehouse_id === $id)>{{ $n }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">{{ __('Refund from') }}</label><select name="refund_bank_account_id" class="form-select"><option value="">{{ __('No refund (keep as credit)') }}</option>@foreach($banks as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="restock" value="1" id="rs" checked><label class="form-check-label" for="rs">{{ __('Return items to stock (good condition)') }}</label></div></div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <button class="btn btn-outline-primary">{{ __('Save draft') }}</button>
        @can('credit_notes.approve')<button class="btn btn-danger" name="post_now" value="1" data-confirm="{{ __('Post the credit note now?') }}">{{ __('Save & post') }}</button>@endcan
    </div>
</form>
<script>
    // live refund preview (the server recalculates exactly on save)
    (function () {
        const inputs = document.querySelectorAll('[data-return-qty]');
        const fmt = (n) => (Math.round(n * 1000) / 1000).toFixed(3);
        const update = () => {
            let total = 0;
            inputs.forEach((el) => {
                const line = (parseFloat(el.value) || 0) * parseFloat(el.dataset.net) * (1 + (parseFloat(el.dataset.tax) || 0) / 100);
                el.closest('tr').querySelector('[data-return-line]').textContent = fmt(line);
                total += line;
            });
            document.querySelector('[data-return-total]').textContent = fmt(total);
        };
        inputs.forEach((el) => el.addEventListener('input', update));
    })();
</script>
@endsection
