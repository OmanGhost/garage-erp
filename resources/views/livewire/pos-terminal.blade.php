<div x-data x-on:barcode-scanned.window="$wire.scanned($event.detail.code)" x-on:pos-print.window="window.open($event.detail.url, '_blank', 'width=420,height=700')"
     x-on:keydown.window.f2.prevent="$refs.posSearch.focus(); $refs.posSearch.select()" x-on:keydown.window.escape="$wire.inquiryId && $wire.closeInquiry()">
    @php
        $typeBadge = ['genuine' => 'primary', 'oem' => 'primary', 'aftermarket' => 'info', 'used' => 'warning', 'refurbished' => 'warning', 'damaged' => 'danger', 'scrap' => 'secondary'];
    @endphp
    <div class="pos-wrap">
        <div class="d-flex flex-column gap-3 min-w-0">
            {{-- search bar --}}
            <div class="pos-searchbar">
                <div class="pos-search">
                    <i class="bi bi-upc-scan"></i>
                    <input type="search" x-ref="posSearch" autofocus wire:model.live.debounce.250ms="search" wire:keydown.enter="searchEnter"
                           placeholder="{{ __('Scan barcode or search name / SKU / OEM...') }}" aria-label="{{ __('Search') }}">
                    <span class="pos-kbd d-none d-md-inline">F2</span>
                </div>
                <button type="button" class="btn btn-primary pos-inquiry-btn" wire:click="inquireSearch" title="{{ __('Show full details of the part') }}">
                    <i class="bi bi-info-circle"></i> <span>{{ __('Part inquiry') }}</span>
                </button>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 pos-filters">
                <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Part type') }}">
                    @foreach(['' => __('All'), 'new' => __('New'), 'used' => __('Used')] as $k => $l)
                        <button type="button" class="btn {{ $typeFilter === $k ? 'btn-dark' : 'btn-outline-secondary' }}" wire:click="$set('typeFilter', '{{ $k }}')">{{ $l }}</button>
                    @endforeach
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="posStockOnly" wire:model.live="stockOnly">
                    <label class="form-check-label small" for="posStockOnly">{{ __('Available here only') }}</label>
                </div>
                @if($results->isNotEmpty())<span class="small text-body-secondary ms-auto">{{ trans_choice('{1} 1 result|[2,*] :n results', $results->count(), ['n' => $results->count()]) }}</span>@endif
            </div>

            {{-- results --}}
            @if($results->isNotEmpty())
                <div class="pos-results">
                    @foreach($results as $p)
                        @php
                            $s = $stock[$p->id] ?? null;
                            $avail = $s ? $s->available() : '0';
                            $total = \App\Support\Money::max('0', \App\Support\Money::sub($p->on_hand ?? 0, $p->reserved ?? 0));
                            $img = $p->imageUrl();
                        @endphp
                        <div class="pos-result {{ isset($inCart[$p->id]) ? 'in-cart' : '' }}" wire:key="r-{{ $p->id }}">
                            <button type="button" class="pos-result-main" wire:click="add({{ $p->id }})" title="{{ __('Add to cart') }}">
                                <span class="pos-thumb">@if($img)<img src="{{ $img }}" alt="" loading="lazy">@else<i class="bi bi-gear-wide-connected"></i>@endif</span>
                                <span class="min-w-0 text-start">
                                    <span class="d-block fw-semibold text-truncate">{{ $p->displayName() }}</span>
                                    <span class="d-block small text-body-secondary text-truncate">
                                        <bdi dir="ltr" class="font-monospace">{{ $p->sku }}</bdi>@if($p->oem_number) · OEM <bdi dir="ltr" class="font-monospace">{{ $p->oem_number }}</bdi>@endif
                                        @if($p->brand) · {{ $p->brand->name }}@endif
                                    </span>
                                    <span class="d-flex flex-wrap gap-1 mt-1">
                                        <span class="badge text-bg-{{ $typeBadge[$p->part_type] ?? 'secondary' }}">{{ status_label($p->part_type) }}{{ $p->condition_grade ? ' '.$p->condition_grade : '' }}</span>
                                        @if(isset($inCart[$p->id]))<span class="badge text-bg-dark"><i class="bi bi-cart-check"></i> {{ qty($inCart[$p->id]) }}</span>@endif
                                    </span>
                                </span>
                            </button>
                            <div class="pos-result-stock">
                                <span class="pos-stock {{ (float) $avail > 0 ? 'ok' : ((float) $total > 0 ? 'elsewhere' : 'none') }}">
                                    <b>{{ qty($avail) }}</b><small>{{ __('here') }}</small>
                                </span>
                                @if((float) $total !== (float) $avail)<small class="text-body-secondary">{{ __('All') }}: {{ qty($total) }}</small>@endif
                            </div>
                            <div class="pos-result-price">{{ money($p->priceFor($priceLevel), false) }}</div>
                            <div class="pos-result-actions">
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="inquire({{ $p->id }})" title="{{ __('Part inquiry') }}" aria-label="{{ __('Part inquiry') }}"><i class="bi bi-info-lg"></i></button>
                                <button type="button" class="btn btn-sm btn-success" wire:click="add({{ $p->id }})" title="{{ __('Add to cart') }}" aria-label="{{ __('Add to cart') }}"><i class="bi bi-plus-lg"></i></button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="pos-empty">
                    @if(mb_strlen(trim($search)) >= 2)
                        <i class="bi bi-emoji-frown"></i><div>{{ __('No parts match your search.') }}</div>
                        @if($stockOnly || $typeFilter)<button class="btn btn-sm btn-link" wire:click="clearFilters">{{ __('Clear filters') }}</button>@endif
                    @else
                        <i class="bi bi-upc-scan"></i><div class="fw-semibold">{{ __('Search or scan a product to add it to the cart.') }}</div>
                        <div class="small text-body-secondary">{{ __('Click a result to add it, or the i button for full details: stock in every warehouse, alternatives, fitment and sales history.') }}</div>
                    @endif
                </div>
            @endif

            @if($held->isNotEmpty())
                <div class="card"><div class="card-header"><i class="bi bi-pause-circle me-1"></i>{{ __('Held invoices') }}</div>
                    <div class="list-group list-group-flush">
                        @foreach($held as $h)
                            <div class="list-group-item d-flex align-items-center gap-2">
                                <span class="badge text-bg-warning">{{ $h->held_label }}</span><span>{{ $h->customer?->name }}</span>
                                <span class="ms-auto font-monospace">{{ money($h->total, false) }}</span>
                                <button class="btn btn-sm btn-outline-primary" wire:click="resume({{ $h->id }})">{{ __('Resume') }}</button>
                                <button class="btn btn-sm btn-outline-danger" wire:click="discardHeld({{ $h->id }})" data-confirm="{{ __('Delete this held invoice?') }}" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- cart --}}
        <div class="card pos-cart" id="posCart">
            <div class="pos-cart-head">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold"><i class="bi bi-cart3"></i> {{ __('Cart') }}</span>
                    <span class="badge rounded-pill text-bg-primary">{{ count($cart) }}</span>
                    @if($heldInvoiceId)<span class="badge text-bg-warning">{{ __('Resumed') }}</span>@endif
                    <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="{{ __('Price level') }}">
                        @foreach(['retail' => __('Retail'), 'wholesale' => __('Wholesale')] as $k => $l)
                            <button type="button" class="btn {{ $priceLevel === $k ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="$set('priceLevel', '{{ $k }}')">{{ $l }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="pos-customer">
                    <i class="bi bi-person-circle"></i>
                    <span class="fw-semibold text-truncate">{{ $customerName }}</span>
                </div>
                <div wire:ignore x-data x-on:lookup-selected="$wire.pickCustomer($event.detail.id)">
                    <x-lookup name="pos_customer" type="customers" size="sm" :placeholder="__('Change customer (name / phone)...')"/>
                </div>
            </div>
            <div class="cart-lines">
                @forelse($cart as $i => $line)
                    @php $q = (float) $line['qty']; $belowMin = (float) $line['min'] > 0 && (float) $line['unit_price'] < (float) $line['min']; @endphp
                    <div class="pos-line" wire:key="line-{{ $i }}-{{ $line['part_id'] }}">
                        <div class="d-flex align-items-start gap-2">
                            <button type="button" class="btn btn-link p-0 text-start flex-grow-1 min-w-0 text-decoration-none text-reset" wire:click="inquire({{ $line['part_id'] }})" title="{{ __('Part inquiry') }}">
                                <span class="d-block fw-semibold small text-truncate">{{ $line['name'] }}</span>
                                <span class="d-block small text-body-secondary font-monospace" dir="ltr">{{ $line['sku'] }}</span>
                            </button>
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
                                <input type="number" step="0.001" min="0" class="form-control {{ $belowMin ? 'is-invalid' : '' }}" wire:model.live.debounce.500ms="cart.{{ $i }}.unit_price" aria-label="{{ __('Price') }}"></div>
                            <div class="input-group input-group-sm"><span class="input-group-text">{{ __('Disc.') }}</span>
                                <input type="number" step="0.001" min="0" class="form-control" wire:model.live.debounce.500ms="cart.{{ $i }}.discount" aria-label="{{ __('Discount') }}"></div>
                        </div>
                        @if($belowMin)<div class="small text-danger mt-1"><i class="bi bi-exclamation-triangle"></i> {{ __('Below the minimum price :p', ['p' => money($line['min'], false)]) }}</div>@endif
                    </div>
                @empty
                    <div class="pos-empty pos-empty-cart"><i class="bi bi-cart"></i><div>{{ __('Cart is empty') }}</div></div>
                @endforelse
            </div>
            <div class="pos-cart-foot">
                <div class="pos-sum"><span>{{ __('Subtotal') }}</span><span class="font-monospace">{{ money($totals['subtotal'], false) }}</span></div>
                @if(\App\Support\Money::isPositive($totals['discount_total']))<div class="pos-sum text-danger"><span>{{ __('Discount') }}</span><span class="font-monospace">-{{ money($totals['discount_total'], false) }}</span></div>@endif
                <div class="pos-sum"><span>{{ __('VAT') }}</span><span class="font-monospace">{{ money($totals['tax_total'], false) }}</span></div>
                <div class="pos-grand"><span>{{ __('Total') }}</span><span class="pos-total">{{ money($totals['total']) }}</span></div>

                <div class="pos-pay">
                    @foreach($payments as $pi => $p)
                        <div class="input-group input-group-sm mb-1" wire:key="pay-{{ $pi }}">
                            <select class="form-select" style="max-width:130px" wire:model.live="payments.{{ $pi }}.method" aria-label="{{ __('Payment method') }}">
                                <option value="cash">{{ __('Cash') }}</option><option value="card">{{ __('Card') }}</option>
                                <option value="bank_transfer">{{ __('Bank transfer') }}</option><option value="wallet">{{ __('E-wallet') }}</option>
                            </select>
                            <input type="number" step="0.001" min="0" class="form-control font-monospace" wire:model.live.debounce.400ms="payments.{{ $pi }}.amount" placeholder="0.000" aria-label="{{ __('Amount') }}">
                            @if(count($payments) > 1)<button class="btn btn-outline-danger" wire:click="removePayment({{ $pi }})" aria-label="{{ __('Remove') }}"><i class="bi bi-dash"></i></button>@endif
                        </div>
                    @endforeach
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-secondary flex-fill" wire:click="payExact"><i class="bi bi-check2"></i> {{ __('Exact amount') }}</button>
                        <button class="btn btn-sm btn-outline-secondary flex-fill" wire:click="addPayment"><i class="bi bi-plus"></i> {{ __('Split payment') }}</button>
                    </div>
                    <div class="d-flex justify-content-between small mt-2">
                        <span>{{ __('Paid') }}: <b class="font-monospace">{{ money($paid, false) }}</b></span>
                        @if(\App\Support\Money::isPositive($due))<span class="text-warning-emphasis">{{ __('Remaining (credit)') }}: <b class="font-monospace">{{ money($due, false) }}</b></span>@endif
                        @if(\App\Support\Money::isPositive($change))<span class="text-success">{{ __('Change') }}: <b class="font-monospace">{{ money($change, false) }}</b></span>@endif
                    </div>
                </div>
                <input type="text" class="form-control form-control-sm mt-2" wire:model="notes" placeholder="{{ __('Notes') }}" aria-label="{{ __('Notes') }}">
                <button class="btn btn-success btn-lg w-100 mt-2 pos-checkout" wire:click="checkout" wire:loading.attr="disabled" @disabled(empty($cart))>
                    <span wire:loading.remove wire:target="checkout"><i class="bi bi-check2-circle"></i> {{ __('Complete sale') }}</span>
                    <span wire:loading wire:target="checkout"><span class="spinner-border spinner-border-sm"></span></span>
                </button>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-outline-warning flex-fill" wire:click="hold" @disabled(empty($cart))><i class="bi bi-pause"></i> {{ __('Hold') }}</button>
                    <button class="btn btn-outline-secondary flex-fill" wire:click="clearCart" data-confirm="{{ __('Clear the cart?') }}"><i class="bi bi-trash"></i> {{ __('Clear') }}</button>
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

    {{-- part inquiry panel --}}
    @if($inquiry)
        @include('livewire.partials.pos-inquiry', ['q' => $inquiry, 'typeBadge' => $typeBadge])
    @endif

    {{-- phones / tablets: the cart is below the products; this bar shows the total and jumps to it --}}
    <div class="pos-mobile-bar d-xl-none">
        <div class="min-w-0">
            <div class="small text-body-secondary">{{ trans_choice('{0} Cart is empty|{1} 1 item|[2,*] :n items', count($cart), ['n' => count($cart)]) }}</div>
            <div class="fw-bold fs-5 font-monospace">{{ money($totals['total']) }}</div>
        </div>
        <a href="#posCart" class="btn btn-primary ms-auto"><i class="bi bi-cart3"></i> {{ __('Cart') }} <span class="badge text-bg-light">{{ count($cart) }}</span></a>
    </div>
</div>
