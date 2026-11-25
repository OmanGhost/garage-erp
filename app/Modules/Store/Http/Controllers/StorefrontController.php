<?php

namespace App\Modules\Store\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Company;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\PartCategory;
use App\Modules\Inventory\Services\PartSearch;
use App\Modules\Store\Models\OnlinePayment;
use App\Modules\Store\Models\StoreOrder;
use App\Modules\Store\Services\PaymentGateway;
use App\Modules\Store\Services\StoreService;
use App\Modules\Vehicles\Models\VehicleMake;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public online parts store per company (/shop/{slug}), synchronized with ERP stock.
 */
class StorefrontController extends Controller
{
    protected function enter(Company $company): void
    {
        abort_unless($company->is_active && $company->moduleEnabled('store'), 404);
        tenant()->set($company->id);
    }

    public function home(Request $request, Company $company)
    {
        $this->enter($company);
        $f = $request->only(['q', 'make_id', 'model_id', 'year', 'part_category_id', 'brand_id', 'part_type']);
        $parts = PartSearch::query($f)->where('is_online', true)->where('is_active', true)->paginate(24)->withQueryString();

        return view('store::home', ['company' => $company, 'parts' => $parts, 'f' => $f, 'cartCount' => array_sum(app(StoreService::class)->cart()),
            'makes' => VehicleMake::query()->orderBy('name')->pluck('name', 'id'), 'brands' => Brand::query()->orderBy('name')->pluck('name', 'id'),
            'categories' => PartCategory::query()->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])]);
    }

    public function part(Company $company, int $part)
    {
        $this->enter($company);
        $p = Part::query()->where('is_online', true)->with('brand', 'category', 'compatibilities.make', 'compatibilities.vehicleModel')->findOrFail($part);

        return view('store::part', ['company' => $company, 'p' => $p, 'available' => $p->available(app(StoreService::class)->onlineWarehouseId()), 'cartCount' => array_sum(app(StoreService::class)->cart())]);
    }

    public function addToCart(Request $request, Company $company)
    {
        $this->enter($company);
        $data = $request->validate(['part_id' => ['required', 'integer'], 'qty' => ['nullable', 'integer', 'min:1', 'max:99']]);
        app(StoreService::class)->add($data['part_id'], $data['qty'] ?? 1);

        return back()->with('success', __('Added to cart.'));
    }

    public function updateCart(Request $request, Company $company)
    {
        $this->enter($company);
        $svc = app(StoreService::class);
        $svc->setCart(array_map('intval', (array) $request->input('qty', [])));

        return back();
    }

    public function cart(Company $company)
    {
        $this->enter($company);

        return view('store::cart', ['company' => $company, 'lines' => app(StoreService::class)->lines(), 'cartCount' => array_sum(app(StoreService::class)->cart())]);
    }

    public function checkout(Company $company)
    {
        $this->enter($company);
        $lines = app(StoreService::class)->lines();
        if (! $lines) {
            return redirect()->route('store.cart', $company);
        }

        return view('store::checkout', ['company' => $company, 'lines' => $lines, 'gateway' => app(PaymentGateway::class)->enabled(), 'cartCount' => 0, 'user' => auth()->user()]);
    }

    public function placeOrder(Request $request, Company $company, StoreService $store, PaymentGateway $gateway)
    {
        $this->enter($company);
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:190'], 'customer_phone' => ['required', 'string', 'max:30'], 'customer_email' => ['nullable', 'email'],
            'shipping_method' => ['required', 'in:pickup,delivery'], 'shipping_address' => ['required_if:shipping_method,delivery', 'nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'in:cod,online'], 'coupon' => ['nullable', 'string', 'max:40'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $order = $store->placeOrder($data);
        if ($data['payment_method'] === 'online' && $gateway->enabled()) {
            $p = OnlinePayment::create(['payable_type' => $order->getMorphClass(), 'payable_id' => $order->id, 'gateway' => $gateway->driver(), 'amount' => $order->total,
                'status' => 'pending', 'session_id' => Str::random(40), 'reference' => $order->number]);

            return redirect()->away($gateway->start($p, route('payments.return', $p), route('store.order', [$company, $order->uuid]), ['name' => $order->customer_name]));
        }

        return redirect()->route('store.order', [$company, $order->uuid])->with('success', __('Thank you! Your order :n has been received.', ['n' => $order->number]));
    }

    public function order(Company $company, string $uuid)
    {
        $this->enter($company);

        return view('store::order', ['company' => $company, 'o' => StoreOrder::query()->where('uuid', $uuid)->with('items')->firstOrFail(), 'cartCount' => 0]);
    }

    // ------------------------------------------------------ payment gateway

    protected function findPayment(string $session): OnlinePayment
    {
        $p = OnlinePayment::query()->withoutGlobalScopes()->where('session_id', $session)->firstOrFail();
        tenant()->set($p->company_id);

        return $p;
    }

    /** Built-in test checkout (sandbox driver only). */
    public function sandbox(string $session, PaymentGateway $gateway)
    {
        $p = $this->findPayment($session);
        abort_unless($gateway->driver() === 'sandbox' && $p->status === 'pending', 404);

        return view('store::sandbox', ['p' => $p, 'company' => tenant()->company()]);
    }

    public function sandboxComplete(Request $request, string $session, PaymentGateway $gateway)
    {
        $p = $this->findPayment($session);
        abort_unless($gateway->driver() === 'sandbox' && $p->status === 'pending', 404);
        if ($request->input('result') === 'paid') {
            $p->update(['status' => 'paid']);
            $gateway->complete($p);
        } else {
            $p->update(['status' => 'failed']);
        }

        return redirect()->route('payments.return', $p);
    }

    public function paymentReturn(int $payment, PaymentGateway $gateway)
    {
        $p = OnlinePayment::query()->withoutGlobalScopes()->findOrFail($payment);
        tenant()->set($p->company_id);
        if ($p->status === 'pending' && $gateway->verify($p)) {
            $p->update(['status' => 'paid']);
            $gateway->complete($p);
        }
        $p->refresh();
        $company = tenant()->company();
        $target = $p->payable instanceof StoreOrder ? route('store.order', [$company, $p->payable->uuid]) : (auth()->check() ? route('portal.invoices') : url('/'));

        return redirect($target)->with($p->status === 'paid' ? 'success' : 'error', $p->status === 'paid' ? __('Payment received. Thank you!') : __('Payment was not completed.'));
    }
}
