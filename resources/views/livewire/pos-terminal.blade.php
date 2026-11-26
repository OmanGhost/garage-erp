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
                                <div class="small text-body-secondary text-truncate"><bdi dir="ltr" class="font-monospace">{{ $p->sku }}</bdi>@if($p->oem_number) · <bdi dir="ltr" class="font-monospace">{{ $p->oem_number }}</bdi>@endif</div>
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
                                <button class="btn btn-sm btn-outline-danger" wire:click="discardHeld({{ $h->id }})" data-confirm="{{ __('Delete this held invoice?') }}"><i class="bi bi-trash"></i></button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="card pos-cart" id="posCart">
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
            {{-- cart lines as stacked rows: readable on phones and in the narrow desktop column --}}
            <div class="cart-lines">
                @forelse($cart as $i => $line)
                    @php $q = (float) $line['qty']; @endphp
                    <div class="pos-line" wire:key="line-{{ $i }}-{{ $line['part_id'] }}">
                        <div class="d-flex align-items-start gap-2">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold small text-truncate">{{ $line['name'] }}</div>
                                <div class="small text-body-secondary font-monospace" dir="ltr">{{ $line['sku'] }}</div>
                            </div>
                            <div class="fw-bold font-monospace">{{ money($totals['lines'][$i]['net'] ?? 0, false) }}</div>
                            <button class="btn btn-sm btn-link text-danger p-0 ms-1" wire:click="remove({{ $i }})" aria-label="{{ __('Remove') }}" title="{{ __('Remove') }}"><i class="bi bi-x-circle fs-5"></i></button>
                        </div>
                        <div class="pos-line-controls">
                            <div class="input-group input-group-sm pos-qty" role="group" aria-label="{{ __('Qty') }}">
                                <button class="btn btn-outline-secondary" type="button" wire:click="$set('cart.{{ $i }}.qty', {{ max(1, $q - 1) }})" @disabled($q <= 1) aria-label="−">−</button>
                                <input type="number" step="1" min="0.001" class="form-control text-center" wire:model.live.debounce.400ms="cart.{{ $i }}.qty" aria-label="{{ __('Qty') }}">
                                <button class="btn btn-outline-secondary" type="button" wire:click="$set('cart.{{ $i }}.qty', {{ $q + 1 }})" aria-label="+">+</button>
                            </div>
                            <div class="input-group input-group-sm"><span class="input-group-text">{{ __('Price') }}</span>
                                <input type="number" step="0.001" min="0" class="form-control" wire:model.live.debounce.500ms="cart.{{ $i }}.unit_price" aria-label="{{ __('Price') }}"></div>
                            <div class="input-group input-group-sm"><span class="input-group-text">{{ __('Disc.') }}</span>
                                <input type="number" step="0.001" min="0" class="form-control" wire:model.live.debounce.500ms="cart.{{ $i }}.discount" aria-label="{{ __('Discount') }}"></div>
                        </div>
                    </div>
                @empty
                    <div class="empty-state py-4"><i class="bi bi-cart"></i>{{ __('Cart is empty') }}</div>
                @endforelse
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
                        <button class="btn btn-outline-secondary" wire:click="clearCart" data-confirm="{{ __('Clear the cart?') }}"><i class="bi bi-trash"></i> {{ __('Clear') }}</button>
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

    {{-- phones / tablets: the cart is below the products; this bar shows the total and jumps to it --}}
    <div class="pos-mobile-bar d-xl-none">
        <div class="min-w-0">
            <div class="small text-body-secondary">{{ trans_choice('{0} Cart is empty|{1} 1 item|[2,*] :n items', count($cart), ['n' => count($cart)]) }}</div>
            <div class="fw-bold fs-5 font-monospace">{{ money($totals['total']) }}</div>
        </div>
        <a href="#posCart" class="btn btn-primary ms-auto"><i class="bi bi-cart3"></i> {{ __('Cart') }} <span class="badge text-bg-light">{{ count($cart) }}</span></a>
    </div>
</div>
