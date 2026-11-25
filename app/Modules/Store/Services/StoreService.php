<?php

namespace App\Modules\Store\Services;

use App\Exceptions\BusinessException;
use App\Modules\CRM\Models\Customer;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Store\Models\Coupon;
use App\Modules\Store\Models\StoreOrder;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Totals;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Online store: session cart, coupons, checkout, order processing and
 * invoicing (stock is issued when the order is invoiced = synchronized with ERP).
 */
class StoreService
{
    public function cart(): array
    {
        return session('store_cart.'.tenant()->id(), []);
    }

    public function setCart(array $cart): void
    {
        session(['store_cart.'.tenant()->id() => array_filter($cart, fn ($q) => $q > 0)]);
    }

    public function add(int $partId, int $qty = 1): void
    {
        $part = Part::query()->where('is_online', true)->where('is_active', true)->findOrFail($partId);
        $cart = $this->cart();
        $cart[$part->id] = min(($cart[$part->id] ?? 0) + max(1, $qty), 99);
        $this->setCart($cart);
    }

    public function onlineWarehouseId(): ?int
    {
        return Warehouse::query()->where('type', 'online')->value('id') ?? Warehouse::query()->where('type', 'main')->value('id');
    }

    public function lines(): array
    {
        $cart = $this->cart();
        $parts = Part::query()->whereIn('id', array_keys($cart))->where('is_online', true)->get()->keyBy('id');
        $lines = [];
        foreach ($cart as $id => $qty) {
            if ($p = $parts[$id] ?? null) {
                $c = Totals::line($qty, $p->sale_price, 0, $p->tax_rate);
                $lines[] = ['part' => $p, 'qty' => $qty, 'unit_price' => (string) $p->sale_price, 'tax_rate' => (string) $p->tax_rate,
                    'net' => $c['net'], 'tax' => $c['tax'], 'available' => $p->available($this->onlineWarehouseId())];
            }
        }

        return $lines;
    }

    public function coupon(?string $code, string $subtotal): ?array
    {
        if (! $code) {
            return null;
        }
        $c = Coupon::query()->where('code', strtoupper(trim($code)))->where('is_active', true)->first();
        if (! $c || ($c->starts_at && $c->starts_at->isFuture()) || ($c->ends_at && $c->ends_at->endOfDay()->isPast())
            || ($c->max_uses && $c->used_count >= $c->max_uses) || Money::cmp($subtotal, $c->min_order) < 0) {
            throw new BusinessException(__('This coupon is not valid.'));
        }
        $discount = $c->type === 'percent' ? Money::percent($subtotal, $c->value) : Money::min($c->value, $subtotal);

        return ['coupon' => $c, 'discount' => $discount];
    }

    public function placeOrder(array $data): StoreOrder
    {
        $lines = $this->lines();
        if (! $lines) {
            throw new BusinessException(__('Your cart is empty.'));
        }

        return DB::transaction(function () use ($data, $lines) {
            foreach ($lines as $l) {
                if (Money::cmp($l['qty'], $l['available']) > 0) {
                    throw new BusinessException(__(':p is not available in the requested quantity.', ['p' => $l['part']->displayName()]));
                }
            }
            $subtotal = Money::sum(array_column($lines, 'net'));
            $coupon = $this->coupon($data['coupon'] ?? null, $subtotal);
            $discount = $coupon['discount'] ?? '0';
            $ratio = Money::isPositive($subtotal) ? Money::div(Money::sub($subtotal, $discount), $subtotal, 8) : '1';
            $tax = Money::sum(array_map(fn ($l) => Money::percent(Money::mul($l['net'], $ratio, 6), $l['tax_rate']), $lines));
            $shipping = ($data['shipping_method'] ?? 'pickup') === 'delivery' ? Money::round(setting('store.delivery_fee', '2.000')) : '0';
            $customer = auth()->user()?->customer;
            $order = StoreOrder::create([
                'number' => DocumentNumber::next('store_order'), 'customer_id' => $customer?->id,
                'customer_name' => $data['customer_name'], 'customer_phone' => omani_phone($data['customer_phone']),
                'customer_email' => $data['customer_email'] ?? null, 'shipping_method' => $data['shipping_method'] ?? 'pickup',
                'shipping_address' => $data['shipping_address'] ?? null, 'shipping_fee' => $shipping, 'coupon_id' => $coupon['coupon']->id ?? null,
                'subtotal' => $subtotal, 'discount_total' => $discount, 'tax_total' => $tax,
                'total' => Money::add(Money::sub($subtotal, $discount), $tax, $shipping), 'status' => 'pending',
                'payment_method' => $data['payment_method'] ?? 'cod', 'payment_status' => 'unpaid',
                'warehouse_id' => $this->onlineWarehouseId(), 'notes' => $data['notes'] ?? null, 'uuid' => (string) Str::uuid(),
            ]);
            foreach ($lines as $l) {
                $order->items()->create(['part_id' => $l['part']->id, 'description' => $l['part']->displayName(), 'qty' => $l['qty'],
                    'unit_price' => $l['unit_price'], 'tax_rate' => $l['tax_rate'], 'tax_amount' => $l['tax'], 'line_total' => $l['net']]);
            }
            if ($coupon) {
                $coupon['coupon']->increment('used_count');
            }
            $this->setCart([]);

            return $order;
        });
    }

    /** Create & post the ERP invoice for an order (issues stock). */
    public function invoice(StoreOrder $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            $order = StoreOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->invoice_id) {
                return $order->invoice;
            }
            $customer = $order->customer ?? Customer::query()->where('phone', $order->customer_phone)->first() ?? Customer::create([
                'code' => DocumentNumber::next('customer', false), 'name' => $order->customer_name, 'phone' => $order->customer_phone,
                'email' => $order->customer_email, 'source' => 'online',
            ]);
            $ratio = Money::isPositive($order->subtotal) ? Money::div($order->discount_total, $order->subtotal, 8) : '0';
            $items = $order->items->map(fn ($i) => ['item_type' => 'part', 'part_id' => $i->part_id, 'description' => $i->description, 'qty' => $i->qty,
                'unit_price' => $i->unit_price, 'discount' => Money::mul($i->line_total, $ratio), 'tax_rate' => $i->tax_rate, 'warehouse_id' => $order->warehouse_id])->all();
            if (Money::isPositive($order->shipping_fee)) {
                $items[] = ['item_type' => 'other', 'description' => __('Delivery fee'), 'qty' => 1, 'unit_price' => $order->shipping_fee, 'tax_rate' => 0];
            }
            $svc = app(InvoiceService::class);
            $invoice = $svc->post($svc->createDraft(['type' => 'store', 'customer_id' => $customer->id, 'store_order_id' => $order->id,
                'warehouse_id' => $order->warehouse_id, 'payment_terms' => 'credit', 'notes' => __('Online order').' '.$order->number], $items), true);
            $order->update(['invoice_id' => $invoice->id, 'customer_id' => $customer->id, 'status' => $order->status === 'pending' ? 'confirmed' : $order->status]);

            return $invoice;
        });
    }
}
