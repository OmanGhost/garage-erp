{{-- Cash session closing summary: receipts per payment method and the expected cash in the drawer --}}
<div class="row g-2 mb-3 text-center">
    <div class="col-4"><div class="border rounded p-2"><div class="small text-body-secondary">{{ __('Invoices') }}</div><div class="fs-5 fw-bold">{{ $sm['invoices'] }}</div></div></div>
    <div class="col-4"><div class="border rounded p-2"><div class="small text-body-secondary">{{ __('Total sales') }}</div><div class="fs-5 fw-bold font-monospace">{{ money($sm['sales'], false) }}</div></div></div>
    <div class="col-4"><div class="border rounded p-2"><div class="small text-body-secondary">{{ __('Not in the drawer') }}</div><div class="fs-5 fw-bold font-monospace">{{ money($sm['other_total'], false) }}</div></div></div>
</div>
<h6 class="mb-1">{{ __('Receipts by payment method') }}</h6>
<div class="table-responsive mb-3"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>{{ __('Method') }}</th><th class="text-end">{{ __('Received') }}</th><th class="text-end">{{ __('Refunded') }}</th><th class="text-end">{{ __('Net') }}</th>
        @if((float) ($sm['fees'] ?? 0) > 0)<th class="text-end">{{ __('Bank commission') }}</th><th class="text-end">{{ __('To the bank') }}</th>@endif</tr></thead>
    <tbody>
    @forelse($sm['methods'] as $m)
        <tr>
            <td>{{ status_label($m['method']) }} @if($m['drawer'])<span class="badge text-bg-success">{{ __('Cash drawer') }}</span>@endif</td>
            <td class="text-end font-monospace">{{ money($m['in'], false) }}</td>
            <td class="text-end font-monospace {{ (float) $m['out'] > 0 ? 'text-danger' : '' }}">{{ money($m['out'], false) }}</td>
            <td class="text-end font-monospace fw-semibold">{{ money($m['net'], false) }}</td>
            @if((float) ($sm['fees'] ?? 0) > 0)
                <td class="text-end font-monospace text-danger">{{ (float) $m['fee'] > 0 ? '-'.money($m['fee'], false) : '' }}</td>
                <td class="text-end font-monospace">{{ (float) $m['fee'] > 0 ? money(\App\Support\Money::sub($m['net'], $m['fee']), false) : '' }}</td>
            @endif
        </tr>
    @empty
        <tr><td colspan="4" class="text-body-secondary">{{ __('No payments in this session.') }}</td></tr>
    @endforelse
    </tbody>
</table></div>

<h6 class="mb-1">{{ __('Expected cash in the drawer') }}</h6>
<table class="table table-sm mb-3">
    <tr><td>{{ __('Opening cash (float)') }}</td><td class="text-end font-monospace">{{ money($sm['opening'], false) }}</td></tr>
    <tr><td>+ {{ __('Cash received') }}</td><td class="text-end font-monospace">{{ money($sm['cash_in'], false) }}</td></tr>
    <tr><td>− {{ __('Cash refunded / paid out') }}</td><td class="text-end font-monospace">{{ money($sm['cash_out'], false) }}</td></tr>
    <tr class="fw-bold table-light" data-expected="{{ (float) $sm['expected'] }}"><td>= {{ __('Expected cash') }}</td><td class="text-end font-monospace">{{ money($sm['expected'], false) }}</td></tr>
</table>
