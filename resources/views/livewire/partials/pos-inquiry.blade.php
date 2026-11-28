{{-- Part inquiry side panel of the POS: everything needed to decide on a part --}}
@php
    $p = $q['part'];
    $img = $p->imageUrl();
    $here = $q['here'];
    $hereAvail = (float) ($here['available'] ?? 0);
@endphp
<div class="pos-inq-backdrop" wire:click="closeInquiry"></div>
<aside class="pos-inq" role="dialog" aria-modal="true" aria-labelledby="posInqTitle" wire:key="inq-{{ $p->id }}">
    <header class="pos-inq-head">
        <span class="pos-inq-img">@if($img)<img src="{{ $img }}" alt="">@else<i class="bi bi-gear-wide-connected"></i>@endif</span>
        <div class="min-w-0 flex-grow-1">
            <h2 class="h5 mb-1" id="posInqTitle">{{ $p->displayName() }}</h2>
            @if($p->name_en && $p->name_en !== $p->displayName())<div class="small text-body-secondary">{{ $p->name_en }}</div>@endif
            <div class="d-flex flex-wrap gap-1 mt-1">
                <span class="badge text-bg-{{ $typeBadge[$p->part_type] ?? 'secondary' }}">{{ status_label($p->part_type) }}{{ $p->condition_grade ? ' '.$p->condition_grade : '' }}</span>
                @if($p->brand)<span class="badge text-bg-light border">{{ $p->brand->name }}</span>@endif
                @if($p->category)<span class="badge text-bg-light border">{{ $p->category->displayName() }}</span>@endif
                @if(! $p->is_active)<span class="badge text-bg-danger">{{ __('Inactive') }}</span>@endif
            </div>
        </div>
        <button type="button" class="btn-close" wire:click="closeInquiry" aria-label="{{ __('Close') }}"></button>
    </header>

    <div class="pos-inq-body">
        {{-- decision strip --}}
        <div class="pos-inq-kpis">
            <div><small>{{ __('Price') }} ({{ $priceLevel === 'wholesale' ? __('Wholesale') : __('Retail') }})</small><b class="font-monospace">{{ money($q['price'], false) }}</b></div>
            <div class="{{ $hereAvail > 0 ? 'ok' : 'bad' }}"><small>{{ __('Available here') }}</small><b>{{ qty($here['available'] ?? 0) }}</b></div>
            <div><small>{{ __('All warehouses') }}</small><b>{{ qty($q['total_available']) }}</b></div>
            <div><small>{{ __('Sold / month') }}</small><b>{{ $q['sales']['per_month'] }}</b></div>
        </div>
        @if($hereAvail <= 0 && (float) $q['total_available'] > 0)
            <div class="alert alert-warning py-2 small"><i class="bi bi-truck"></i> {{ __('Not available in this branch — available in another warehouse (see below).') }}</div>
        @elseif((float) $q['total_available'] <= 0)
            <div class="alert alert-danger py-2 small"><i class="bi bi-x-octagon"></i> {{ __('Out of stock.') }}
                @if($q['incoming']) {{ __('On order: :q expected :d.', ['q' => qty($q['incoming'][0]['qty']), 'd' => $q['incoming'][0]['expected'] ?? '—']) }}@endif
                @if($q['alternatives']) {{ __('See the alternatives below.') }}@endif</div>
        @elseif($q['low'])
            <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle"></i> {{ __('Low stock (minimum :m).', ['m' => qty($p->min_stock)]) }}</div>
        @endif

        <div class="d-flex gap-2 mb-3">
            <button type="button" class="btn btn-success flex-fill" wire:click="addFromInquiry({{ $p->id }})" @disabled(! $p->is_active)><i class="bi bi-cart-plus"></i> {{ __('Add to cart') }}
                @if(isset($inCart[$p->id]))<span class="badge text-bg-light ms-1">{{ qty($inCart[$p->id]) }}</span>@endif</button>
        </div>

        {{-- identification --}}
        <section class="pos-inq-sec">
            <h3>{{ __('Identification') }}</h3>
            <dl class="pos-inq-dl">
                <dt>SKU</dt><dd class="font-monospace" dir="ltr">{{ $p->sku }}</dd>
                @if($p->oem_number)<dt>OEM</dt><dd class="font-monospace" dir="ltr">{{ $p->oem_number }}</dd>@endif
                @if($p->barcode)<dt>{{ __('Barcode') }}</dt><dd class="font-monospace" dir="ltr">{{ $p->barcode }}</dd>@endif
                <dt>{{ __('Unit') }}</dt><dd>{{ $p->unit }}</dd>
                @if($p->country_of_origin)<dt>{{ __('Origin') }}</dt><dd>{{ $p->country_of_origin }}</dd>@endif
                @if($p->warranty_days || $p->warranty_km)<dt>{{ __('Warranty') }}</dt><dd>{{ collect([$p->warranty_days ? $p->warranty_days.' '.__('days') : null, $p->warranty_km ? number_format($p->warranty_km).' '.__('km') : null])->filter()->implode(' / ') }}</dd>@endif
                @if((float) $p->core_charge > 0)<dt>{{ __('Core charge') }}</dt><dd class="font-monospace">{{ money($p->core_charge, false) }}</dd>@endif
                @if($p->track_serial)<dt>{{ __('Serial numbers') }}</dt><dd><i class="bi bi-check2"></i></dd>@endif
            </dl>
            @if($p->description)<p class="small text-body-secondary mb-0 mt-2">{{ $p->description }}</p>@endif
        </section>

        {{-- prices --}}
        <section class="pos-inq-sec">
            <h3>{{ __('Prices') }}</h3>
            <div class="pos-inq-prices">
                <div class="{{ $priceLevel === 'retail' ? 'active' : '' }}"><small>{{ __('Retail') }}</small><b>{{ money($q['prices']['retail'], false) }}</b></div>
                <div class="{{ $priceLevel === 'wholesale' ? 'active' : '' }}"><small>{{ __('Wholesale') }}</small><b>{{ money($q['prices']['wholesale'], false) }}</b></div>
                <div><small>{{ __('Workshop') }}</small><b>{{ money($q['prices']['workshop'], false) }}</b></div>
                @if((float) $q['prices']['min'] > 0)<div><small>{{ __('Minimum') }}</small><b>{{ money($q['prices']['min'], false) }}</b></div>@endif
            </div>
            <div class="small text-body-secondary mt-1">{{ __('VAT :r% not included', ['r' => rtrim(rtrim($q['prices']['tax_rate'], '0'), '.')]) }}</div>
            @if($q['customer_last'])
                <div class="pos-inq-note mt-2"><i class="bi bi-person-check"></i> {{ __('This customer last bought it on :d at :p (:n).', ['d' => $q['customer_last']['date'], 'p' => money($q['customer_last']['price'], false), 'n' => $q['customer_last']['number']]) }}</div>
            @endif
            @if($q['cost'])
                <div class="pos-inq-cost mt-2">
                    <span>{{ __('Cost') }}: <b class="font-monospace">{{ money($q['cost']['average'], false) }}</b></span>
                    @if($q['cost']['margin'] !== null)<span>{{ __('Margin') }}: <b class="{{ $q['cost']['margin'] < 10 ? 'text-danger' : 'text-success' }}">{{ $q['cost']['margin'] }}%</b></span>@endif
                    @if($q['cost']['last_supplier'])<span>{{ __('Last supplier') }}: <b>{{ $q['cost']['last_supplier'] }}</b> ({{ money($q['cost']['last_price'], false) }}@if($q['cost']['lead_days']) · {{ $q['cost']['lead_days'] }} {{ __('days') }}@endif)</span>@endif
                </div>
            @endif
        </section>

        {{-- stock --}}
        <section class="pos-inq-sec">
            <h3>{{ __('Stock by warehouse') }}</h3>
            @if($q['stock'])
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Warehouse') }}</th><th>{{ __('Location') }}</th><th class="text-end">{{ __('On hand') }}</th><th class="text-end">{{ __('Reserved') }}</th><th class="text-end">{{ __('Available') }}</th></tr></thead>
                    <tbody>
                    @foreach($q['stock'] as $s)
                        <tr class="{{ $s['here'] ? 'table-primary' : '' }}">
                            <td>{{ $s['warehouse'] }} @if($s['here'])<span class="badge text-bg-primary">{{ __('here') }}</span>@endif</td>
                            <td class="font-monospace small">{{ $s['location'] ?? '—' }}</td>
                            <td class="text-end">{{ qty($s['on_hand']) }}</td>
                            <td class="text-end text-body-secondary">{{ qty($s['reserved']) }}</td>
                            <td class="text-end fw-bold {{ (float) $s['available'] > 0 ? 'text-success' : 'text-danger' }}">{{ qty($s['available']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <div class="small text-body-secondary">{{ __('Never stocked.') }}</div>
            @endif
            @if($q['incoming'])
                <div class="mt-2 small"><b><i class="bi bi-truck"></i> {{ __('On order') }}:</b>
                    @foreach($q['incoming'] as $in) <span class="badge text-bg-info">{{ qty($in['qty']) }} · {{ $in['expected'] ?? '—' }} · {{ $in['number'] }}</span>@endforeach
                </div>
            @endif
        </section>

        {{-- alternatives --}}
        <section class="pos-inq-sec">
            <h3>{{ __('Alternatives & interchangeable parts') }} <span class="badge text-bg-secondary">{{ count($q['alternatives']) }}</span></h3>
            @forelse($q['alternatives'] as $a)
                <div class="pos-inq-alt">
                    <div class="min-w-0 flex-grow-1">
                        <button type="button" class="btn btn-link p-0 text-start text-decoration-none fw-semibold text-truncate d-block mw-100" wire:click="inquire({{ $a['id'] }})">{{ $a['name'] }}</button>
                        <div class="small text-body-secondary text-truncate"><bdi dir="ltr" class="font-monospace">{{ $a['sku'] }}</bdi>@if($a['brand']) · {{ $a['brand'] }}@endif
                            · <span class="badge text-bg-{{ $typeBadge[$a['type']] ?? 'secondary' }}">{{ status_label($a['type']) }}{{ $a['grade'] ? ' '.$a['grade'] : '' }}</span></div>
                    </div>
                    <span class="text-end small"><b class="d-block font-monospace">{{ money($a['price'], false) }}</b>
                        <span class="{{ (float) $a['available'] > 0 ? 'text-success' : 'text-danger' }}">{{ qty($a['available']) }} {{ __('available') }}</span></span>
                    <button type="button" class="btn btn-sm btn-outline-success" wire:click="addFromInquiry({{ $a['id'] }})" @disabled(! $a['active']) aria-label="{{ __('Add to cart') }}"><i class="bi bi-plus-lg"></i></button>
                </div>
            @empty
                <div class="small text-body-secondary">{{ __('No alternatives recorded.') }}</div>
            @endforelse
        </section>

        {{-- fitment --}}
        @if($q['fits'])
            <section class="pos-inq-sec">
                <h3>{{ __('Fits vehicles') }}</h3>
                <ul class="list-unstyled mb-0 small">
                    @foreach($q['fits'] as $f)
                        <li class="py-1 border-bottom"><i class="bi bi-car-front text-primary"></i> <b>{{ $f['vehicle'] }}</b>@if($f['years']) · {{ $f['years'] }}@endif @if($f['engine']) · {{ $f['engine'] }}@endif @if($f['notes'])<span class="text-body-secondary"> — {{ $f['notes'] }}</span>@endif</li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- sales --}}
        <section class="pos-inq-sec">
            <h3>{{ __('Sales') }}</h3>
            <div class="pos-inq-prices">
                <div><small>{{ __('Last 30 days') }}</small><b>{{ qty($q['sales']['30']) }}</b></div>
                <div><small>{{ __('Last 90 days') }}</small><b>{{ qty($q['sales']['90']) }}</b></div>
                <div><small>{{ __('Last sold') }}</small><b class="small">{{ $q['sales']['last'] ?? '—' }}</b></div>
                <div><small>{{ __('Stock covers') }}</small><b class="small">{{ $q['sales']['cover_days'] !== null ? $q['sales']['cover_days'].' '.__('days') : '—' }}</b></div>
            </div>
        </section>
    </div>
</aside>
