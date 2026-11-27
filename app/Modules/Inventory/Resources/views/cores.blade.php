@extends('layouts.app')
@section('title', __('Core returns'))
@section('content')
<x-page-header :title="__('Core returns')" icon="bi-arrow-return-left"/>
<p>{{ __('Pending deposits') }} <strong>{{ money($pendingTotal) }}</strong></p>
<nav class="nav nav-pills mb-3">
    @foreach(['pending', 'returned', 'forfeited'] as $key)
        <a class="nav-link {{ $key === $status ? 'active' : '' }}" href="{{ request()->url() }}?status={{ $key }}">{{ status_label($key) }}</a>
    @endforeach
</nav>
<div class="card mb-3"><div class="table-responsive"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Part') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Deposit') }}</th><th>{{ __('Due date') }}</th><th>{{ __('Actions') }}</th></tr></thead>
    <tbody>@forelse($rows as $r)
        <tr><td><a href="{{ route('invoices.show', $r->invoice_id) }}">{{ $r->invoice?->number }}</a></td><td>{{ $r->customer?->name }}</td><td>{{ $r->part?->label() }}</td><td>{{ qty($r->qty) }}</td><td>{{ money($r->amount) }}</td><td>{{ $r->due_date?->toDateString() }}</td>
            <td>@if($r->status === 'pending') @can('credit_notes.create')
            <form method="post" action="{{ route('inventory.cores.action', [$r, 'forfeit']) }}" class="d-flex gap-2">@csrf <x-idem/>
                <select name="condition" class="form-select" aria-label="{{ __('Condition') }}"><option value="good">{{ __('Good') }}</option><option value="damaged">{{ __('Damaged') }}</option></select>
                <input class="form-control" name="notes" placeholder="{{ __('Notes') }}" maxlength="190">
                @can('credit_notes.approve')<button class="btn btn-primary" formaction="{{ route('inventory.cores.action', [$r, 'receive']) }}">{{ __('Refund deposit') }}</button>@endcan
                <button class="btn btn-outline-danger" data-confirm="{{ __('Forfeit deposit?') }}">{{ __('Forfeit') }}</button>
            </form>
            @endcan @elseif($r->credit_note_id)<a href="{{ route('credit-notes.show', $r->credit_note_id) }}">{{ __('Credit note') }}</a>@endif</td></tr>
    @empty<tr><td colspan="7">{{ __('No records') }}</td></tr>@endforelse</tbody>
</table></div></div>
{{ $rows->links() }}
@endsection
