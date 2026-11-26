@extends('layouts.admin')
@section('title', $inv->number)
@section('content')
@php
    $st = $inv->stage();
    $stColor = ['unpaid' => 'warning', 'overdue' => 'danger', 'paid' => 'success', 'cancelled' => 'secondary'][$st] ?? 'secondary';
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-receipt text-brand"></i> <span class="font-monospace">{{ $inv->number }}</span> <span class="badge text-bg-{{ $stColor }} align-middle fs-6">{{ status_label($st) }}</span></h4>
        <div class="small text-body-secondary"><a href="{{ route('admin.clients.show', $inv->company_id) }}">{{ $inv->company?->name }}</a> · {{ __('Issued on') }} {{ $inv->issue_date?->format('Y-m-d') }}@if($inv->creator) · {{ $inv->creator->name }}@endif</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admin.billing.index') }}"><i class="bi bi-arrow-right"></i> {{ __('Back') }}</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.billing.print', $inv) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Print') }}</a>
        <a class="btn btn-outline-danger" href="{{ route('admin.billing.print', [$inv, 'pdf']) }}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body row g-3 small">
                <div class="col-6 col-md-3"><div class="text-body-secondary">{{ __('Issue date') }}</div><div class="fw-semibold">{{ $inv->issue_date?->format('Y-m-d') }}</div></div>
                <div class="col-6 col-md-3"><div class="text-body-secondary">{{ __('Due date') }}</div><div class="fw-semibold {{ $st === 'overdue' ? 'text-danger' : '' }}">{{ $inv->due_date?->format('Y-m-d') }}</div></div>
                <div class="col-6 col-md-3"><div class="text-body-secondary">{{ __('Period') }}</div><div class="fw-semibold">@if($inv->period_start){{ $inv->period_start->format('Y-m-d') }} → {{ $inv->period_end?->format('Y-m-d') }}@else — @endif</div></div>
                <div class="col-6 col-md-3"><div class="text-body-secondary">{{ __('Plan') }}</div><div class="fw-semibold">{{ $inv->subscription?->plan?->name ?? $inv->company?->plan?->name ?? '—' }}</div></div>
            </div>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead class="table-light small"><tr><th>#</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Unit price') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                @foreach($inv->items ?? [] as $n => $it)
                    <tr><td>{{ $n + 1 }}</td><td style="white-space:normal">{{ $it['description'] }}</td><td class="text-end">{{ qty($it['qty']) }}</td>
                        <td class="text-end font-monospace">{{ money($it['unit_price'], false) }}</td><td class="text-end font-monospace">{{ money(\App\Support\Money::mul($it['qty'], $it['unit_price']), false) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot class="small">
                    <tr><td colspan="4" class="text-end">{{ __('Subtotal') }}</td><td class="text-end font-monospace">{{ money($inv->subtotal, false) }}</td></tr>
                    @if(\App\Support\Money::isPositive($inv->discount))<tr class="text-danger"><td colspan="4" class="text-end">{{ __('Discount') }}</td><td class="text-end font-monospace">-{{ money($inv->discount, false) }}</td></tr>@endif
                    <tr><td colspan="4" class="text-end">{{ __('VAT') }} ({{ rtrim(rtrim((string) $inv->tax_rate, '0'), '.') ?: 0 }}%)</td><td class="text-end font-monospace">{{ money($inv->tax_amount, false) }}</td></tr>
                    <tr class="fw-bold fs-6"><td colspan="4" class="text-end">{{ __('Total') }}</td><td class="text-end font-monospace">{{ money($inv->amount) }}</td></tr>
                </tfoot>
            </table></div>
            @if($inv->notes)<div class="card-footer small" style="white-space:pre-line">{{ $inv->notes }}</div>@endif
        </div>
    </div>

    <div class="col-lg-4">
        @if($st === 'paid')
            <div class="card border-success mb-3"><div class="card-body">
                <div class="fw-semibold text-success mb-1"><i class="bi bi-check-circle-fill"></i> {{ __('Paid') }}</div>
                <div class="small">{{ __('Date') }}: {{ $inv->paid_at?->format('Y-m-d') }}</div>
                @if($inv->payment_method)<div class="small">{{ __('Payment method') }}: {{ status_label($inv->payment_method) }}</div>@endif
                @if($inv->payment_reference)<div class="small">{{ __('Reference') }}: <bdi dir="ltr">{{ $inv->payment_reference }}</bdi></div>@endif
            </div></div>
        @elseif($st === 'cancelled')
            <div class="card border-secondary mb-3"><div class="card-body">
                <div class="fw-semibold mb-1"><i class="bi bi-x-octagon"></i> {{ __('Cancelled') }} · {{ $inv->cancelled_at?->format('Y-m-d') }}</div>
                <div class="small">{{ $inv->cancel_reason }}</div>
            </div></div>
        @else
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-cash-coin"></i> {{ __('Record payment') }}</div>
                <form class="card-body row g-2" method="post" action="{{ route('admin.billing.pay', $inv) }}" data-confirm="{{ __('Mark this invoice as paid?') }}">@csrf
                    <div class="col-12"><label class="form-label small mb-0" for="p_method">{{ __('Payment method') }}</label><select id="p_method" name="payment_method" class="form-select">@foreach(\App\Modules\Core\Models\SubscriptionInvoice::METHODS as $m)<option value="{{ $m }}">{{ status_label($m) }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label small mb-0" for="p_date">{{ __('Date') }}</label><input id="p_date" type="date" name="paid_on" class="form-control" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}"></div>
                    <div class="col-6"><label class="form-label small mb-0" for="p_ref">{{ __('Reference') }}</label><input id="p_ref" name="payment_reference" class="form-control"></div>
                    <div class="col-12 d-grid"><button class="btn btn-success"><i class="bi bi-check2"></i> {{ __('Mark paid') }}</button></div>
                </form>
            </div>
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-x-octagon"></i> {{ __('Cancel invoice') }}</div>
                <form class="card-body row g-2" method="post" action="{{ route('admin.billing.cancel', $inv) }}" data-confirm="{{ __('Cancel this invoice?') }}">@csrf
                    <div class="col-12"><input name="reason" class="form-control" required maxlength="250" placeholder="{{ __('Reason') }}" aria-label="{{ __('Reason') }}"></div>
                    <div class="col-12 d-grid"><button class="btn btn-outline-danger">{{ __('Cancel invoice') }}</button></div>
                </form>
            </div>
        @endif
        <div class="card"><div class="card-body small">
            <div class="fw-semibold mb-1">{{ $inv->company?->name }}</div>
            @if($inv->company?->vat_number)<div>{{ __('VAT number') }}: <bdi dir="ltr">{{ $inv->company->vat_number }}</bdi></div>@endif
            @if($inv->company?->email)<div><bdi dir="ltr">{{ $inv->company->email }}</bdi></div>@endif
            @if($inv->company?->phone)<div><bdi dir="ltr">{{ $inv->company->phone }}</bdi></div>@endif
            <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('admin.billing.create', ['company_id' => $inv->company_id]) }}"><i class="bi bi-plus"></i> {{ __('New invoice for this client') }}</a>
        </div></div>
    </div>
</div>
@endsection
