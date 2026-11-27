@extends('layouts.app')
@section('title', __('Subcontracted repairs'))
@section('content')
<x-page-header :title="__('Subcontracted repairs')" icon="bi-buildings" :breadcrumbs="[__('Workshop'), __('Subcontracted repairs')]" />

<div class="row g-3 mb-3">
    @foreach([
        ['out', __('Out with subcontractors'), 'bi-box-arrow-up-right', 'primary', false],
        ['overdue', __('Overdue returns'), 'bi-alarm', 'danger', false],
        ['month_cost', __('Outside cost this month'), 'bi-cash-coin', 'secondary', true],
        ['month_sell', __('Billed to customers this month'), 'bi-receipt', 'success', true],
        ['warranty', __('Under outside warranty'), 'bi-shield-check', 'info', false],
    ] as [$k, $l, $i, $c, $m])
        <div class="col-6 col-lg">
            <div class="card h-100"><div class="card-body d-flex align-items-center gap-2">
                <i class="bi {{ $i }} fs-3 text-{{ $c }}"></i>
                <div><div class="small text-body-secondary">{{ $l }}</div><div class="fs-5 fw-bold">{{ $m ? money($stats[$k]) : $stats[$k] }}</div></div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <div class="btn-group btn-group-sm" role="group">
            @foreach(['open' => __('Open'), 'draft' => status_label('draft'), 'sent_out' => status_label('sent_out'), 'back_in' => status_label('back_in'), 'cancelled' => status_label('cancelled'), 'all' => __('All')] as $k => $l)
                <a href="{{ request()->fullUrlWithQuery(['status' => $k, 'page' => null]) }}" class="btn btn-outline-secondary {{ $status === $k ? 'active' : '' }}">{{ $l }}</a>
            @endforeach
        </div>
        <form method="get" class="ms-auto d-flex gap-1">
            <input type="hidden" name="status" value="{{ $status }}">
            <select name="supplier_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('Supplier') }}">
                <option value="">{{ __('All suppliers') }}</option>
                @foreach($suppliers as $id => $n)<option value="{{ $id }}" @selected((int) request('supplier_id') === $id)>{{ $n }}</option>@endforeach
            </select>
        </form>
    </div>
    <div class="table-responsive"><table class="table table-erp align-middle mb-0">
        <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Job card') }}</th><th>{{ __('Subcontractor') }}</th><th>{{ __('Work') }}</th><th>{{ __('Expected back') }}</th>
            <th class="text-end">{{ __('Cost') }}</th><th class="text-end">{{ __('Customer price') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($orders as $o)
            <tr class="{{ $o->isOverdue() ? 'table-danger' : '' }}">
                <td class="fw-semibold">{{ $o->number }}</td>
                <td><a href="{{ route('job-cards.show', $o->job_card_id) }}">{{ $o->jobCard?->number }}</a><div class="small text-body-secondary">{{ $o->jobCard?->vehicle?->plate() }}</div></td>
                <td>{{ $o->supplier?->name }}</td>
                <td><div>{{ $o->description }}</div><div class="small text-body-secondary">{{ __(ucfirst($o->work_type)) }}@if($o->item_sent) · {{ $o->item_sent }}@endif</div></td>
                <td class="small">{{ $o->expected_at?->format('Y-m-d') ?? '—' }}@if($o->isOverdue())<div class="text-danger fw-semibold">{{ __('Overdue') }}</div>@endif
                    @if($o->warranty_until)<div class="{{ $o->underWarranty() ? 'text-success' : 'text-body-secondary' }}"><i class="bi bi-shield-check"></i> {{ $o->warranty_until->format('Y-m-d') }}</div>@endif</td>
                <td class="text-end font-monospace">{{ money($o->actual_cost ?? $o->quoted_cost, false) }}@if($o->actual_cost === null)<div class="small text-body-secondary">{{ __('quoted') }}</div>@endif</td>
                <td class="text-end font-monospace">{{ money($o->sell_price, false) }}</td>
                <td>{!! status_badge($o->status) !!}
                    @if($o->supplierInvoice)<div class="small"><a href="{{ route('supplier-invoices.show', $o->supplier_invoice_id) }}">{{ $o->supplierInvoice->number }}</a></div>@endif</td>
                <td class="text-end text-nowrap">@include('workshop::subcontracts._actions', ['o' => $o])</td>
            </tr>
        @empty
            <tr><td colspan="9"><div class="empty-state"><i class="bi bi-buildings"></i>{{ __('No outside work orders. Create them from the job card.') }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($orders->hasPages())<div class="card-footer">{{ $orders->links() }}</div>@endif
</div>
@endsection
