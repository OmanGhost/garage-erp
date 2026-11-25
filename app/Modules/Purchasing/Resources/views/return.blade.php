@extends('layouts.app')
@section('title', $r->number)
@section('content')
<x-page-header :title="__('Purchase return').' '.$r->number" icon="bi-arrow-return-left" :breadcrumbs="[__('Purchase Returns') => route('crud.index', 'purchase-returns'), $r->number]"
    :subtitle="e($r->supplier->name).' · '.e($r->reason).' · '.status_badge($r->status)">
    @if($r->status === 'draft')@can('purchase_returns.approve')<form method="post" action="{{ route('purchase-returns.post', $r) }}" data-confirm="{{ __('Post the return? Stock will be issued and the supplier account debited.') }}">@csrf <x-idem/><button class="btn btn-success">{{ __('Post') }}</button></form>@endcan @endif
</x-page-header>
<div class="card"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Qty') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end">{{ __('VAT') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
    <tbody>@foreach($r->items as $i)<tr><td>{{ $i->part->label() }}</td><td>{{ qty($i->qty) }}</td><td class="text-end font-monospace">{{ money($i->unit_cost, false) }}</td><td class="text-end font-monospace">{{ money($i->tax_amount, false) }}</td><td class="text-end font-monospace">{{ money($i->line_total, false) }}</td></tr>@endforeach</tbody>
    <tfoot><tr class="fw-bold"><td colspan="4" class="text-end">{{ __('Total incl. VAT') }}</td><td class="text-end font-monospace">{{ money($r->total) }}</td></tr></tfoot>
</table></div>
@endsection
