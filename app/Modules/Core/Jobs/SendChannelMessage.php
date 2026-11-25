<?php

namespace App\Modules\Core\Jobs;

use App\Modules\Core\Models\NotificationLog;
use App\Modules\Core\Services\IntegrationManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Queued delivery of a notification log entry on its channel.
 */
class SendChannelMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public function __construct(public int $logId, public int $companyId) {}

    public function handle(): void
    {
        tenant()->set($this->companyId);
        $log = NotificationLog::query()->find($this->logId);
        if (! $log || $log->status === 'sent') {
            return;
        }
        try {
            $result = match ($log->channel) {
                'mail' => (function () use ($log) {
                    IntegrationManager::sendMail($log->recipient, $log->subject ?: config('app.name'), $log->body);

                    return ['ok' => true];
                })(),
                'sms' => IntegrationManager::sendSms($log->recipient, $log->body),
                'whatsapp' => IntegrationManager::sendWhatsApp($log->recipient, $log->body),
                default => ['ok' => false, 'body' => 'unknown channel'],
            };
            $log->update(['status' => $result['ok'] ? 'sent' : 'failed', 'error' => $result['ok'] ? null : ($result['body'] ?? null)]);
            if (! $result['ok']) {
                $this->release(120);
            }
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }
    }
}
