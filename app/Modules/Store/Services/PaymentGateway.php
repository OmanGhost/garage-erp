<?php

namespace App\Modules\Store\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Core\Services\IntegrationManager;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Services\PaymentService;
use App\Modules\Store\Models\OnlinePayment;
use App\Modules\Store\Models\StoreOrder;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Online payment abstraction. Drivers:
 *  - sandbox : built-in test checkout page (no external service, non-production use)
 *  - thawani : Thawani Pay (Oman) checkout sessions. Verify endpoints/fields against
 *              the provider's current documentation before going live.
 * The gateway is optional and disabled until configured in Integrations.
 */
class PaymentGateway
{
    public function driver(): ?string
    {
        $i = IntegrationManager::active('payment');
        if (! $i) {
            return null;
        }

        return $i->credentials['driver'] ?? 'sandbox';
    }

    public function enabled(): bool
    {
        return $this->driver() !== null;
    }

    public function start(OnlinePayment $p, string $successUrl, string $cancelUrl, array $customer = []): string
    {
        return match ($this->driver()) {
            'thawani' => $this->thawaniStart($p, $successUrl, $cancelUrl, $customer),
            'sandbox' => route('payments.sandbox', $p->session_id),
            default => throw new BusinessException(__('Online payment is not enabled.')),
        };
    }

    protected function thawaniStart(OnlinePayment $p, string $successUrl, string $cancelUrl, array $customer): string
    {
        $c = IntegrationManager::active('payment')->credentials;
        $base = rtrim($c['base_url'] ?? 'https://uatcheckout.thawani.om', '/');
        $res = Http::timeout(20)->withHeaders(['thawani-api-key' => $c['secret_key'] ?? ''])->post($base.'/api/v1/checkout/session', [
            'client_reference_id' => (string) $p->id,
            'mode' => 'payment',
            'products' => [['name' => 'Order '.$p->reference, 'quantity' => 1, 'unit_amount' => (int) bcmul(Money::n($p->amount), '1000', 0)]], // baisa
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => ['payable' => $p->payable_type.':'.$p->payable_id, 'customer' => $customer['name'] ?? null],
        ]);
        $session = $res->json('data.session_id');
        if (! $res->successful() || ! $session) {
            throw new BusinessException(__('Could not start the online payment.'));
        }
        $p->update(['session_id' => $session, 'payload' => $res->json()]);

        return $base.'/pay/'.$session.'?key='.($c['publishable_key'] ?? '');
    }

    /** Ask the provider whether the session is paid. */
    public function verify(OnlinePayment $p): bool
    {
        if ($this->driver() === 'thawani') {
            $c = IntegrationManager::active('payment')->credentials;
            $base = rtrim($c['base_url'] ?? 'https://uatcheckout.thawani.om', '/');
            $res = Http::timeout(20)->withHeaders(['thawani-api-key' => $c['secret_key'] ?? ''])->get($base.'/api/v1/checkout/session/'.$p->session_id);

            return $res->successful() && $res->json('data.payment_status') === 'paid';
        }

        return $p->status === 'paid';
    }

    /** Mark paid (idempotent) and record the receipt in the ERP. */
    public function complete(OnlinePayment $p): void
    {
        DB::transaction(function () use ($p) {
            $p = OnlinePayment::query()->whereKey($p->id)->lockForUpdate()->firstOrFail();
            if ($p->payment_id) {
                return;
            }
            $bank = BankAccount::query()->where('type', 'card')->value('id') ?? BankAccount::query()->where('type', 'bank')->value('id');
            $payable = $p->payable;
            if ($payable instanceof StoreOrder) {
                $payable->update(['payment_status' => 'paid', 'payment_reference' => $p->session_id]);
                $invoice = app(StoreService::class)->invoice($payable);
            } else {
                $invoice = $payable instanceof Invoice ? $payable : null;
            }
            if (! $invoice) {
                throw new BusinessException('Unsupported payable');
            }
            $payment = app(PaymentService::class)->receiveFromCustomer($invoice->customer, Money::min($p->amount, $invoice->balanceDue()), $bank, 'online',
                [$invoice->id => Money::min($p->amount, $invoice->balanceDue())], ['gateway' => $this->driver(), 'gateway_reference' => $p->session_id]);
            $p->update(['status' => 'paid', 'paid_at' => now(), 'payment_id' => $payment->id]);
        });
    }
}
