<?php

namespace App\Livewire;

use App\Exceptions\BusinessException;
use App\Modules\CRM\Models\Customer;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Models\PosSession;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Sales\Services\PosService;
use App\Support\Money;
use App\Support\Totals;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Fast POS: barcode / name search, retail & wholesale pricing, discounts,
 * hold & resume, split payments, credit sales, 80mm / A4 receipts — and a part inquiry panel (prices, stock per
 * warehouse, on order, alternatives, fitment, sales speed, cost for authorised users) to help the salesperson decide.
 */
class PosTerminal extends Component
{
    public int $sessionId;
    public string $search = '';
    public array $cart = [];
    public ?int $customerId = null;
    public string $customerName = '';
    public string $priceLevel = 'retail';
    public ?int $heldInvoiceId = null;
    public array $payments = [];
    public ?int $lastInvoiceId = null;
    public string $lastChange = '0';
    public string $notes = '';
    public ?int $inquiryId = null;
    public bool $stockOnly = false;
    public string $typeFilter = '';          // '' | new | used

    public function mount(PosSession $session): void
    {
        $this->sessionId = $session->id;
        $this->resetCustomer();
        $this->payments = [['method' => 'cash', 'amount' => '']];
    }

    protected function session(): PosSession
    {
        return PosSession::query()->with('register')->findOrFail($this->sessionId);
    }

    protected function resetCustomer(): void
    {
        $walkIn = Customer::query()->find(setting('pos.walk_in_customer_id')) ?? Customer::query()->where('code', 'WALKIN')->first();
        $this->customerId = $walkIn?->id;
        $this->customerName = $walkIn?->name ?? '';
        $this->priceLevel = 'retail';
    }

    #[On('customer-picked')]
    public function pickCustomer(int $id): void
    {
        $c = Customer::query()->findOrFail($id);
        $this->customerId = $c->id;
        $this->customerName = $c->name;
        $this->priceLevel = $c->price_level ?: 'retail';
        $this->repriceCart();
    }

    public function updatedPriceLevel(): void
    {
        $this->repriceCart();
    }

    protected function repriceCart(): void
    {
        foreach ($this->cart as $k => $line) {
            $p = Part::query()->find($line['part_id']);
            if ($p) {
                $this->cart[$k]['unit_price'] = $p->priceFor($this->priceLevel);
            }
        }
    }

    #[On('barcode-scanned')]
    public function scanned(string $code): void
    {
        $part = Part::query()->where('is_active', true)->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))->first()
            ?? Part::query()->whereHas('batches', fn ($b) => $b->where('serial_number', $code)->where('qty_remaining', '>', 0))->first();
        if (! $part) {
            $this->dispatch('erp-toast', message: __('No part found for barcode :c', ['c' => $code]), type: 'warning');

            return;
        }
        $this->add($part->id);
    }

    public function searchEnter(): void
    {
        $term = trim($this->search);
        if ($term === '') {
            return;
        }
        $exact = Part::query()->where('is_active', true)->where(fn ($q) => $q->where('barcode', $term)->orWhere('sku', $term))->first();
        if ($exact) {
            $this->add($exact->id);
            $this->search = '';
        }
    }

    public function add(int $partId): void
    {
        $part = Part::query()->findOrFail($partId);
        foreach ($this->cart as $k => $line) {
            if ($line['part_id'] === $partId) {
                $this->cart[$k]['qty'] = (string) ((float) $line['qty'] + 1);

                return;
            }
        }
        $this->cart[] = [
            'part_id' => $part->id, 'sku' => $part->sku, 'name' => $part->displayName(),
            'qty' => '1', 'unit_price' => $part->priceFor($this->priceLevel), 'discount' => '0',
            'tax_rate' => (string) $part->tax_rate, 'min' => (string) $part->min_sale_price,
        ];
    }

    /** Open the inquiry panel for a part. */
    public function inquire(int $partId): void
    {
        $this->inquiryId = Part::query()->whereKey($partId)->value('id');
    }

    public function clearFilters(): void
    {
        $this->stockOnly = false;
        $this->typeFilter = '';
    }

    public function closeInquiry(): void
    {
        $this->inquiryId = null;
    }

    /** The "Part inquiry" button: the exact match of the search, or its first result. */
    public function inquireSearch(): void
    {
        $term = trim($this->search);
        if (mb_strlen($term) < 2) {
            $this->dispatch('erp-toast', message: __('Type a part name, number or barcode first.'), type: 'info');

            return;
        }
        // exact barcode / SKU, then the OEM number (genuine before alternatives), then the first search result
        $part = Part::query()->where(fn ($q) => $q->where('barcode', $term)->orWhere('sku', $term))->first()
            ?? Part::query()->where('oem_number', $term)->orderByRaw("CASE part_type WHEN 'genuine' THEN 0 WHEN 'oem' THEN 1 WHEN 'aftermarket' THEN 2 ELSE 3 END")->orderBy('id')->first()
            ?? $this->searchQuery($term)->first();
        if (! $part) {
            $this->dispatch('erp-toast', message: __('No part found for :c', ['c' => $term]), type: 'warning');

            return;
        }
        $this->inquiryId = $part->id;
    }

    /** Add from the inquiry panel and keep it open. */
    public function addFromInquiry(int $partId): void
    {
        $this->add($partId);
        $this->dispatch('erp-toast', message: __('Added to the cart.'), type: 'success');
    }

    protected function searchQuery(string $term)
    {
        $wh = $this->session()->register->warehouse_id;

        return \App\Modules\Inventory\Services\PartSearch::query(['q' => $term])->where('is_active', true)
            ->when($this->typeFilter === 'new', fn ($q) => $q->whereNotIn('part_type', ['used', 'refurbished', 'damaged', 'scrap']))
            ->when($this->typeFilter === 'used', fn ($q) => $q->whereIn('part_type', ['used', 'refurbished']))
            ->when($this->stockOnly, fn ($q) => $q->whereHas('stockLevels', fn ($s) => $s->whereColumn('qty_on_hand', '>', 'qty_reserved')
                ->when($wh, fn ($w) => $w->where('warehouse_id', $wh))));
    }

    public function remove(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->heldInvoiceId = null;
        $this->notes = '';
        $this->payments = [['method' => 'cash', 'amount' => '']];
        $this->resetCustomer();
    }

    public function addPayment(): void
    {
        $this->payments[] = ['method' => 'card', 'amount' => ''];
    }

    public function removePayment(int $i): void
    {
        unset($this->payments[$i]);
        $this->payments = array_values($this->payments) ?: [['method' => 'cash', 'amount' => '']];
    }

    /** Use the customer's credit balance for the sale (the rest stays in cash). */
    public function useBalance(): void
    {
        $customer = $this->customerId ? Customer::query()->find($this->customerId) : null;
        $credit = $customer ? app(\App\Modules\Sales\Services\CustomerCreditService::class)->available($customer) : '0';
        $total = $this->totals()['total'];
        $use = Money::min($credit, $total);
        if (! Money::isPositive($use)) {
            $this->dispatch('erp-toast', message: __('The customer has no credit balance to use.'), type: 'info');

            return;
        }
        $this->payments = [['method' => 'balance', 'amount' => $use]];
        if (Money::cmp($use, $total) < 0) {
            $this->payments[] = ['method' => 'cash', 'amount' => Money::sub($total, $use)];
        }
    }

    public function payExact(): void
    {
        $this->payments = [['method' => $this->payments[0]['method'] ?? 'cash', 'amount' => $this->totals()['total']]];
    }

    public function totals(): array
    {
        $items = array_map(fn ($l) => ['qty' => $l['qty'] ?: 0, 'unit_price' => $l['unit_price'] ?: 0, 'discount' => $l['discount'] ?: 0, 'tax_rate' => $l['tax_rate']], $this->cart);
        $deposits = \App\Modules\Sales\Services\CoreChargeService::depositLines($this->cart);

        return Totals::document(array_merge($items, $deposits));
    }

    protected function cartPayload(): array
    {
        return [
            'customer_id' => $this->customerId, 'price_level' => $this->priceLevel, 'notes' => $this->notes,
            'held_invoice_id' => $this->heldInvoiceId,
            'items' => array_map(fn ($l) => ['part_id' => $l['part_id'], 'qty' => $l['qty'], 'unit_price' => $l['unit_price'], 'discount' => $l['discount'] ?: 0], $this->cart),
        ];
    }

    public function hold(): void
    {
        try {
            app(PosService::class)->hold($this->session(), $this->cartPayload(), $this->customerName);
            $this->clearCart();
            $this->dispatch('erp-toast', message: __('Invoice held.'), type: 'info');
        } catch (BusinessException $e) {
            $this->dispatch('erp-toast', message: $e->getMessage(), type: 'danger');
        }
    }

    public function resume(int $invoiceId): void
    {
        $inv = Invoice::query()->with('items.part', 'customer')->where('status', 'held')->findOrFail($invoiceId);
        $this->cart = $inv->items->where('item_type', '!=', 'core')->values()->map(fn ($i) => [
            'part_id' => $i->part_id, 'sku' => $i->part?->sku, 'name' => $i->description, 'qty' => (string) $i->qty,
            'unit_price' => (string) $i->unit_price, 'discount' => (string) $i->discount, 'tax_rate' => (string) $i->tax_rate,
            'min' => (string) $i->part?->min_sale_price,
        ])->all();
        $this->heldInvoiceId = $inv->id;
        $this->customerId = $inv->customer_id;
        $this->customerName = $inv->customer->name;
        $this->priceLevel = $inv->price_level;
    }

    public function discardHeld(int $invoiceId): void
    {
        $inv = Invoice::query()->where('status', 'held')->findOrFail($invoiceId);
        app(InvoiceService::class)->deleteDraft($inv);
        if ($this->heldInvoiceId === $invoiceId) {
            $this->clearCart();
        }
    }

    /** Cash popup: the amount handed over becomes the cash payment; the change is shown and printed. */
    public function checkoutCash(mixed $tendered): void
    {
        $this->payments = [['method' => 'cash', 'amount' => is_numeric($tendered) ? Money::round($tendered) : '']];
        $this->checkout();
    }

    public function checkout(): void
    {
        if (! auth()->user()->can('pos.create')) {
            $this->dispatch('erp-toast', message: __('Not allowed.'), type: 'danger');

            return;
        }
        $this->validate([
            'cart' => ['required', 'array', 'min:1'],
            'cart.*.qty' => ['required', 'numeric', 'gt:0'],
            'cart.*.unit_price' => ['required', 'numeric', 'min:0'],
            'cart.*.discount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.method' => ['required', 'in:cash,card,bank_transfer,wallet,cheque,balance'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);
        try {
            $payments = array_values(array_filter($this->payments, fn ($p) => Money::isPositive($p['amount'] ?? 0)));
            $r = app(PosService::class)->checkout($this->session(), $this->cartPayload(), $payments);
            $this->lastInvoiceId = $r['invoice']->id;
            $this->lastChange = $r['change'];
            $this->clearCart();
            $this->dispatch('erp-toast', message: __('Sale completed: :n', ['n' => $r['invoice']->number]), type: 'success');
            $this->dispatch('pos-sale-done', change: $r['change'], number: $r['invoice']->number, total: (string) $r['invoice']->total,
                tendered: (string) ($r['invoice']->cash_tendered ?? ''), balance: $r['invoice']->balanceDue());
            $this->dispatch('pos-print', url: route('invoices.print', [$r['invoice'], '80mm']));
        } catch (BusinessException $e) {
            $this->dispatch('erp-toast', message: $e->getMessage(), type: 'danger');
        }
    }

    public function render()
    {
        $session = $this->session();
        $term = trim($this->search);
        $results = collect();
        if (mb_strlen($term) >= 2) {
            $results = $this->searchQuery($term)->limit(30)->get();
        }
        $stock = $results->isNotEmpty() && $session->register->warehouse_id
            ? StockLevel::query()->where('warehouse_id', $session->register->warehouse_id)->whereIn('part_id', $results->pluck('id'))->get()->keyBy('part_id')
            : collect();
        $totals = $this->totals();
        $paid = Money::sum(array_map(fn ($p) => $p['amount'] ?: 0, $this->payments));

        return view('livewire.pos-terminal', [
            'session' => $session, 'results' => $results, 'stock' => $stock, 'totals' => $totals,
            'paid' => $paid, 'change' => Money::max(0, Money::sub($paid, $totals['total'])), 'due' => Money::max(0, Money::sub($totals['total'], $paid)),
            'held' => Invoice::query()->with('customer')->where('status', 'held')->where('pos_session_id', $session->id)->latest()->get(),
            'inquiry' => $this->inquiryId && ($part = Part::query()->find($this->inquiryId))
                ? app(\App\Modules\Inventory\Services\PartInquiry::class)->for($part, $session->register->warehouse_id, $this->customerId, $this->priceLevel,
                    (bool) auth()->user()?->can('reports.view'))
                : null,
            'inCart' => collect($this->cart)->pluck('qty', 'part_id')->all(),
            'customerCredit' => ($c = $this->customerId ? Customer::query()->find($this->customerId) : null)
                ? app(\App\Modules\Sales\Services\CustomerCreditService::class)->available($c) : '0',
        ]);
    }
}
