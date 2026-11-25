<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Jobs\DeliverWebhook;
use App\Modules\Core\Models\Webhook;

/**
 * Outgoing webhooks: HMAC-SHA256 signed JSON (header X-Signature), queued with retries.
 */
class WebhookService
{
    public const EVENTS = ['job_card.status_changed', 'invoice.posted', 'payment.received', 'appointment.created', 'stock.low', 'customer.created'];

    public static function dispatch(string $event, array $payload): void
    {
        if (! tenant()->id()) {
            return;
        }
        foreach (Webhook::query()->where('is_active', true)->get() as $hook) {
            if (in_array($event, $hook->events ?? [], true) || in_array('*', $hook->events ?? [], true)) {
                $delivery = $hook->deliveries()->create(['event' => $event, 'payload' => ['event' => $event, 'data' => $payload, 'sent_at' => now()->toIso8601String()]]);
                DeliverWebhook::dispatch($delivery->id, tenant()->id())->afterCommit();
            }
        }
    }
}
