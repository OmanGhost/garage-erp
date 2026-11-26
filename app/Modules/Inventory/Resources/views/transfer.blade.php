@extends('layouts.app')
@section('title', $t->number)
@section('content')
@php
    $steps = [
        ['done' => true, 'icon' => 'bi-clipboard-plus', 'label' => __('Requested'), 'user' => $t->creator?->name, 'at' => $t->created_at],
        ['done' => (bool) $t->sent_at, 'icon' => 'bi-truck', 'label' => __('Dispatched'), 'user' => $t->sender?->name, 'at' => $t->sent_at],
        ['done' => (bool) $t->received_at, 'icon' => 'bi-box-arrow-in-down', 'label' => __('Received'), 'user' => $t->receiver?->name, 'at' => $t->received_at],
    ];
    $evIcon = ['requested' => 'bi-clipboard-plus text-primary', 'dispatched' => 'bi-truck text-info', 'received' => 'bi-box-arrow-in-down text-success',
               'cancelled' => 'bi-x-octagon text-danger', 'note' => 'bi-chat-left-text text-secondary', 'printed' => 'bi-printer text-body-secondary'];
    $evLabel = ['requested' => __('Transfer requested'), 'dispatched' => __('Dispatched from the warehouse'), 'received' => __('Received at destination'),
                'cancelled' => __('Cancelled'), 'note' => __('Note'), 'printed' => __('Printed')];
    $shortage = $t->items->contains(fn ($i) => $i->received_qty !== null && (float) $i->received_qty < (float) $i->qty);
@endphp
<x-page-header :title="__('Stock transfer').' '.$t->number" icon="bi-truck" :breadcrumbs="[__('Stock Transfers') => route('crud.index', 'stock-transfers'), $t->number]"
    :subtitle="e($t->fromWarehouse->displayName()).' → '.e($t->toWarehouse->displayName()).' · '.status_badge($t->status)">
    @can('stock_transfers.print')
        <div class="btn-group">
            <a class="btn btn-outline-secondary" href="{{ route('inventory.transfers.print', [$t, 'request']) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Print transfer request') }}</a>
            @if($t->status === 'received')<a class="btn btn-outline-success" href="{{ route('inventory.transfers.print', [$t, 'completed']) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Print completed transfer') }}</a>@endif
        </div>
    @endcan
    @can('stock_transfers.update')
        @if($t->status === 'draft')
            <form method="post" action="{{ route('inventory.transfers.action', [$t, 'send']) }}" data-confirm="{{ __('Dispatch the transfer? The stock leaves :w now.', ['w' => $t->fromWarehouse->displayName()]) }}" data-confirm-type="primary">@csrf <x-idem/><button class="btn btn-primary"><i class="bi bi-send"></i> {{ __('Dispatch') }}</button></form>
        @endif
    @endcan
</x-page-header>

@if($t->status === 'cancelled')
    <div class="alert alert-secondary d-flex gap-2"><i class="bi bi-x-octagon fs-5"></i><div>{{ __('Cancelled by :u on :d.', ['u' => $t->canceller?->name ?? '—', 'd' => $t->cancelled_at?->format('Y-m-d H:i') ?? '—']) }} @if($t->cancel_reason)— {{ $t->cancel_reason }}@endif</div></div>
@endif

{{-- tracking --}}
<div class="card mb-3"><div class="card-body">
    <div class="row g-3">
        @foreach($steps as $i => $s)
            <div class="col-md-4 d-flex gap-2 align-items-start">
                <span class="kpi-icon {{ $s['done'] ? 'bg-success-subtle text-success' : 'bg-body-tertiary text-body-tertiary' }}" style="width:40px;height:40px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center"><i class="bi {{ $s['icon'] }}"></i></span>
                <div><div class="fw-semibold">{{ $i + 1 }}. {{ $s['label'] }}</div>
                    <div class="small text-body-secondary">{{ $s['done'] ? (($s['user'] ?? '—').' · '.$s['at']?->format('Y-m-d H:i')) : __('Not yet') }}</div></div>
            </div>
        @endforeach
    </div>
</div></div>

<div class="row g-3">
    <div class="col-xl-8">
        <form method="post" action="{{ route('inventory.transfers.action', [$t, 'receive']) }}" class="card mb-3">@csrf <x-idem/>
            <div class="card-header"><i class="bi bi-box-seam"></i> {{ __('Items') }}</div>
            <div class="table-responsive"><table class="table table-erp mb-0 align-middle">
                <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Qty sent') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th>{{ __('Received') }}</th><th>{{ __('Variance') }}</th></tr></thead>
                <tbody>@foreach($t->items as $i)
                    @php $diff = $i->received_qty !== null ? (float) $i->received_qty - (float) $i->qty : null; @endphp
                    <tr><td>{{ $i->part->label() }}</td><td>{{ qty($i->qty) }}</td><td class="text-end font-monospace">{{ money($i->unit_cost, false) }}</td>
                        <td style="width:150px">@if($t->status === 'in_transit')<input type="number" step="0.001" min="0" max="{{ (float) $i->qty }}" name="received[{{ $i->id }}]" value="{{ (float) $i->qty }}" class="form-control form-control-sm" aria-label="{{ __('Received') }}">@else {{ $i->received_qty !== null ? qty($i->received_qty) : '—' }} @endif</td>
                        <td>@if($diff !== null && $diff < 0)<span class="badge text-bg-danger">{{ qty($diff) }}</span>@elseif($diff !== null)<span class="text-success"><i class="bi bi-check2"></i></span>@else — @endif</td></tr>
                @endforeach</tbody>
            </table></div>
            @if($t->status === 'in_transit')
                @can('stock_transfers.update')
                <div class="card-footer">
                    <input name="notes" class="form-control form-control-sm mb-2" maxlength="500" placeholder="{{ __('Receiving notes (damaged, missing…)') }}" aria-label="{{ __('Notes') }}">
                    <div class="d-flex align-items-center gap-2"><span class="small text-body-secondary">{{ __('Any shortage is posted as inventory loss.') }}</span>
                        <button class="btn btn-success ms-auto"><i class="bi bi-box-arrow-in-down"></i> {{ __('Receive at destination') }}</button></div>
                </div>
                @endcan
            @endif
        </form>
        @if($shortage)<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i> {{ __('Some items were received short; the difference was posted as inventory loss.') }}</div>@endif
        @if($t->notes)<div class="card mb-3"><div class="card-body small"><b>{{ __('Notes') }}:</b> {{ $t->notes }}</div></div>@endif
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clock-history"></i> {{ __('Activity') }}</div>
            <ul class="list-group list-group-flush small">
                @forelse($t->events as $e)
                    <li class="list-group-item d-flex gap-2">
                        <i class="bi {{ $evIcon[$e->event] ?? 'bi-dot' }} fs-6"></i>
                        <div class="flex-grow-1"><div class="fw-semibold">{{ $evLabel[$e->event] ?? status_label($e->event) }}</div>
                            @if($e->notes)<div style="white-space:pre-line">{{ $e->notes }}</div>@endif
                            <div class="text-body-secondary">{{ $e->created_at?->format('Y-m-d H:i') }} · {{ $e->user?->name ?? __('System') }}</div></div>
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary">—</li>
                @endforelse
            </ul>
            @can('stock_transfers.update')
            <div class="card-footer">
                <form method="post" action="{{ route('inventory.transfers.note', $t) }}" class="d-flex gap-2">@csrf
                    <input name="note" class="form-control form-control-sm" required maxlength="500" placeholder="{{ __('Add a note…') }}" aria-label="{{ __('Add a note…') }}">
                    <button class="btn btn-sm btn-outline-primary text-nowrap">{{ __('Add') }}</button>
                </form>
            </div>
            @endcan
        </div>
        @if($t->status === 'draft')
        @can('stock_transfers.update')
            <div class="card border-danger-subtle"><div class="card-body">
                <form method="post" action="{{ route('inventory.transfers.action', [$t, 'cancel']) }}" data-confirm="{{ __('Cancel this transfer request?') }}">@csrf <x-idem/>
                    <input name="reason" class="form-control form-control-sm mb-2" maxlength="250" placeholder="{{ __('Cancellation reason') }}" aria-label="{{ __('Cancellation reason') }}">
                    <button class="btn btn-sm btn-outline-danger w-100">{{ __('Cancel transfer') }}</button>
                </form>
            </div></div>
        @endcan
        @endif
    </div>
</div>
@endsection
