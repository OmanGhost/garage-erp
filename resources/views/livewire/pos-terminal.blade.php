<div x-data x-on:barcode-scanned.window="$wire.scanned($event.detail.code)" x-on:pos-print.window="window.open($event.detail.url, '_blank', 'width=420,height=700')">
    <div class="pos-wrap">
        <div class="d-flex flex-column gap-3 min-w-0">
            <div class="card"><div class="card-body py-2">
                <div class="input-group input-group-lg">
                    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                    <input type="search" class="form-control" autofocus wire:model.live.debounce.250ms="search" wire:keydown.enter="searchEnter"
                           placeholder="{{ __('Scan barcode or search name / SKU / OEM...') }}">
                </div>
            </div></div>
            <div class="row g-2">
                @forelse($results as $p)
                    @php $s = $stock[$p->id] ?? null; $avail = $s ? $s->available() : '0'; @endphp
                    <div class="col-6 col-md-4 col-xxl-3">
                        <div class="card pos-product h-100" wire:click="add({{ $p->id }})">
                            <div class="card-body p-2">
                                <div class="small text-body-secondary">{{ $p->sku }} @if($p->oem_number)· {{ $p->oem_number }}@endif</div>
                                <div class="fw-semibold small" style="min-height:2.4em">{{ \Illuminate\Support\Str::limit($p->displayName(), 60) }}</div>
                                <div class="d-flex justify-content-between align-items-end mt-1">
                                    <span class="fw-bold text-brand">{{ money($p->priceFor($priceLevel), false) }}</span>
                                    <span class="badge text-bg-{{ (float) $avail > 0 ? 'success' : 'danger' }}">{{ qty($avail) }}</span>
                                </div>
                                @if(in_array($p->part_type, ['used', 'refurbished']))<span class="badge text-bg-warning">{{ status_label($p->part_type) }} {{ $p->condition_grade }}</span>@endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12"><div class="empty-state"><i class="bi bi-search"></i>{{ __('Search or scan a product to add it to the cart.') }}</div></div>
                @endforelse
            </div>
            @if($held->isNotEmpty())
                <div class="card"><div class="card-header"><i class="bi bi-pause-circle me-1"></i>{{ __('Held invoices') }}</div>
                    <div class="list-group list-group-flush">
                        @foreach($held as $h)
                            <div class="list-group-item d-flex align-items-center gap-2">
                                <span class="badge text-bg-warning">{{ $h->held_label }}</span><span>{{ $h->customer?->name }}</span>
                                <span class="ms-auto font-monospace">{{ money($h->total, false) }}</span>
                                <button class="btn btn-sm btn-outline-primary" wire:click="resume({{ $h->id }})">{{ __('Resume') }}</button>
                                <button class="btn btn-sm btn-outline-danger" wire:click="discardHeld({{ $h->id }})" wire:confirm="{{ __('Delete this held invoice?') }}"><i class="bi bi-trash"></i></button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="card pos-cart">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-cart3"></i> {{ __('Cart') }}
                @if($heldInvoiceId)<span class="badge text-bg-warning">{{ __('Resumed') }}</span>@endif
                <select class="form-select form-select-sm ms-auto w-auto" wire:model.live="priceLevel">
                    <option value="retail">{{ __('Retail') }}</option><option value="wholesale">{{ __('Wholesale') }}</option>
                </select>
            </div>
            <div class="card-body border-bottom py-2">
                <label class="form-label small mb-1">{{ __('Customer') }}: <strong>{{ $customerName }}</strong></label>
                <div wire:ignore x-data x-on:lookup-selected="$wire.pickCustomer($event.detail.id)">
                    <x-lookup name="pos_customer" type="customers" size="sm" :placeholder="__('Change customer (name / phone)...')"/>
                </div>
            </div>
            <div class="cart-lines">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                    @forelse($cart as $i => $line)
                        <tr wire:key="line-{{ $i }}-{{ $line['part_id'] }}">
                            <td class="ps-3" style="min-width:140px">
                                <div class="small fw-semibold">{{ \Illuminate\Support\Str::limit($line['name'], 40) }}</div>
                                <div class="small text-body-secondary">{{ $line['sku'] }}</div>
                                <div class="input-group input-group-sm mt-1">
                                    <span class="input-group-text">{{ __('Disc.') }}</span>
                                    <input type="number" step="0.001" min="0" class="form-control" wire:model.live.debounce.500ms="cart.{{ $i }}.discount">
                                </div>
                            </td>
                            <td style="width:80px"><input type="number" step="1" min="0.001" class="form-control form-control-sm" wire:model.live.debounce.400ms="cart.{{ $i }}.qty"></td>
                            <td style="width:100px"><input type="number" step="0.001" min="0" class="form-control form-control-sm" wire:model.live.debounce.500ms="cart.{{ $i }}.unit_price"></td>
                            <td class="text-end font-monospace small" style="width:80px">{{ money($totals['lines'][$i]['net'] ?? 0, false) }}</td>
                            <td><button class="btn btn-sm btn-link text-danger" wire:click="remove({{ $i }})"><i class="bi bi-x-lg"></i></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state py-4"><i class="bi bi-cart"></i>{{ __('Cart is empty') }}</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <div class="d-flex justify-content-between small"><span>{{ __('Subtotal') }}</span><span class="font-monospace">{{ money($totals['subtotal'], false) }}</span></div>
                <div class="d-flex justify-content-between small text-danger"><span>{{ __('Discount') }}</span><span class="font-monospace">-{{ money($totals['discount_total'], false) }}</span></div>
                <div class="d-flex justify-content-between small"><span>{{ __('VAT') }}</span><span class="font-monospace">{{ money($totals['tax_total'], false) }}</span></div>
                <div class="d-flex justify-content-between align-items-center mt-1"><span class="fw-bold">{{ __('Total') }}</span><span class="pos-total">{{ money($totals['total']) }}</span></div>

                <div class="mt-2">
                    @foreach($payments as $pi => $p)
                        <div class="input-group input-group-sm mb-1" wire:key="pay-{{ $pi }}">
                            <select class="form-select" style="max-width:130px" wire:model.live="payments.{{ $pi }}.method">
                                <option value="cash">{{ __('Cash') }}</option><option value="card">{{ __('Card') }}</option>
                                <option value="bank_transfer">{{ __('Bank transfer') }}</option><option value="wallet">{{ __('E-wallet') }}</option>
                            </select>
                            <input type="number" step="0.001" min="0" class="form-control font-monospace" wire:model.live.debounce.400ms="payments.{{ $pi }}.amount" placeholder="0.000">
                            @if(count($payments) > 1)<button class="btn btn-outline-danger" wire:click="removePayment({{ $pi }})"><i class="bi bi-dash"></i></button>@endif
                        </div>
                    @endforeach
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-secondary" wire:click="addPayment"><i class="bi bi-plus"></i> {{ __('Split payment') }}</button>
                        <button class="btn btn-sm btn-outline-secondary" wire:click="payExact">{{ __('Exact amount') }}</button>
                    </div>
                    <div class="d-flex justify-content-between small mt-2">
                        <span>{{ __('Paid') }}: <b class="font-monospace">{{ money($paid, false) }}</b></span>
                        @if(\App\Support\Money::isPositive($due))<span class="text-warning">{{ __('Remaining (credit)') }}: <b class="font-monospace">{{ money($due, false) }}</b></span>@endif
                        @if(\App\Support\Money::isPositive($change))<span class="text-success">{{ __('Change') }}: <b class="font-monospace">{{ money($change, false) }}</b></span>@endif
                    </div>
                </div>
                <input type="text" class="form-control form-control-sm mt-2" wire:model="notes" placeholder="{{ __('Notes') }}">
                <div class="d-grid gap-2 mt-2">
                    <button class="btn btn-success btn-lg" wire:click="checkout" wire:loading.attr="disabled" @disabled(empty($cart))>
                        <span wire:loading.remove wire:target="checkout"><i class="bi bi-check2-circle"></i> {{ __('Complete sale') }}</span>
                        <span wire:loading wire:target="checkout"><span class="spinner-border spinner-border-sm"></span></span>
                    </button>
                    <div class="btn-group">
                        <button class="btn btn-outline-warning" wire:click="hold" @disabled(empty($cart))><i class="bi bi-pause"></i> {{ __('Hold') }}</button>
                        <button class="btn btn-outline-secondary" wire:click="clearCart" wire:confirm="{{ __('Clear the cart?') }}"><i class="bi bi-trash"></i> {{ __('Clear') }}</button>
                    </div>
                </div>
                @if($lastInvoiceId)
                    <div class="alert alert-success mt-2 py-2 small mb-0">
                        {{ __('Last sale saved.') }} @if(\App\Support\Money::isPositive($lastChange)) {{ __('Change') }}: <b>{{ money($lastChange) }}</b>@endif
                        <a href="{{ route('invoices.print', [$lastInvoiceId, '80mm']) }}" target="_blank" class="ms-1">80mm</a> ·
                        <a href="{{ route('invoices.print', [$lastInvoiceId, 'a4']) }}" target="_blank">A4</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
