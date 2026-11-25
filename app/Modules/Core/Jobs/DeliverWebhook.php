<?php

namespace App\Modules\Core\Jobs;

use App\Modules\Core\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public array $backoff = [10, 60, 300, 900, 3600];

    public function __construct(public int $deliveryId, public int $companyId) {}

    public function handle(): void
    {
        tenant()->set($this->companyId);
        $d = WebhookDelivery::query()->with('webhook')->find($this->deliveryId);
        if (! $d || $d->delivered_at || ! $d->webhook) {
            return;
        }
        $body = json_encode($d->payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $body, (string) $d->webhook->secret);
        $res = Http::timeout(15)->withHeaders([
            'Content-Type' => 'application/json',
            'X-Event' => $d->event,
            'X-Signature' => 'sha256='.$signature,
            'User-Agent' => 'GarageERP-Webhooks/1.0',
        ])->withBody($body, 'application/json')->post($d->webhook->url);

        $d->update([
            'attempts' => $d->attempts + 1, 'response_code' => $res->status(),
            'response_body' => mb_substr($res->body(), 0, 2000), 'delivered_at' => $res->successful() ? now() : null,
        ]);
        $d->webhook->update(['last_status' => $res->status()]);
        if (! $res->successful()) {
            throw new \RuntimeException('Webhook delivery failed with status '.$res->status());
        }
    }
}
