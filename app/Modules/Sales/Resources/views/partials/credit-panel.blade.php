{{-- Pay a posted invoice from the customer's credit balance (advances, over-payments, credit notes not refunded) --}}
@if(in_array($inv->status, ['posted', 'partially_paid'], true) && $inv->customer && ! $inv->customer->isWalkIn() && auth()->user()->can('payments.create'))
    @php
        $creditAvailable = app(\App\Modules\Sales\Services\CustomerCreditService::class)->available($inv->customer);
        $creditUse = \App\Support\Money::min($creditAvailable, $inv->balanceDue());
    @endphp
    @if(\App\Support\Money::isPositive($creditAvailable))
        <div class="card mb-3 border-success-subtle">
            <div class="card-header fw-semibold"><i class="bi bi-wallet2 text-success me-1"></i>{{ __('Customer credit balance') }}</div>
            <div class="card-body small">
                <div class="d-flex align-items-center mb-2">
                    <span>{{ __('Available') }}</span><b class="ms-auto fs-5 font-monospace text-success">{{ money($creditAvailable) }}</b>
                </div>
                <form method="post" action="{{ route('invoices.pay-from-balance', $inv) }}" class="input-group input-group-sm">@csrf
                    <input type="number" step="0.001" min="0.001" max="{{ $creditUse }}" name="amount" value="{{ $creditUse }}" class="form-control font-monospace" aria-label="{{ __('Amount') }}">
                    <button class="btn btn-success"><i class="bi bi-check2"></i> {{ __('Pay from balance') }}</button>
                </form>
                <div class="form-text">{{ __('No cash moves: the amount is already with you as an advance, over-payment or credit note.') }}</div>
            </div>
        </div>
    @endif
@endif
