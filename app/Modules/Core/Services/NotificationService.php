<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Jobs\SendChannelMessage;
use App\Modules\Core\Models\NotificationLog;
use App\Modules\Core\Models\NotificationTemplate;
use App\Modules\CRM\Models\Customer;
use Illuminate\Database\Eloquent\Model;

/**
 * Renders bilingual editable templates and dispatches messages on the enabled
 * channels (mail, SMS gateway, WhatsApp Business) through the queue.
 * Placeholders: {{customer_name}}, {{number}}, {{total}}, {{link}} ...
 */
class NotificationService
{
    public const EVENTS = [
        'job_card.received' => 'Vehicle received',
        'job_card.status_changed' => 'Job card status changed',
        'job_card.ready' => 'Vehicle ready for delivery',
        'quotation.sent' => 'Quotation sent',
        'additional_work.approval' => 'Additional work approval request',
        'invoice.posted' => 'Invoice issued',
        'payment.received' => 'Payment received',
        'appointment.reminder' => 'Appointment reminder',
        'appointment.booked' => 'Appointment booked',
        'service.reminder' => 'Periodic maintenance reminder',
        'warranty.expiring' => 'Warranty expiring',
    ];

    public function render(string $event, string $channel, array $data, ?string $locale = null): ?array
    {
        $tpl = NotificationTemplate::query()->where('event', $event)->where('channel', $channel)->where('is_active', true)->first();
        if (! $tpl) {
            return null;
        }
        $locale ??= tenant()->company()?->locale ?? 'ar';
        $body = $locale === 'en' ? ($tpl->body_en ?: $tpl->body_ar) : ($tpl->body_ar ?: $tpl->body_en);
        $subject = $locale === 'en' ? ($tpl->subject_en ?: $tpl->subject_ar) : ($tpl->subject_ar ?: $tpl->subject_en);
        $data['company_name'] ??= tenant()->company()?->displayName();
        $replace = [];
        foreach ($data as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $replace['{{'.$k.'}}'] = (string) $v;
                $replace['{{ '.$k.' }}'] = (string) $v;
            }
        }

        return ['subject' => strtr((string) $subject, $replace), 'body' => strtr((string) $body, $replace)];
    }

    /** Notify a customer on the requested channels that are configured & templated. */
    public function notifyCustomer(Customer $customer, string $event, array $data, array $channels = ['mail', 'sms', 'whatsapp'], ?Model $related = null): void
    {
        $data['customer_name'] ??= $customer->name;
        foreach ($channels as $channel) {
            $recipient = match ($channel) {
                'mail' => $customer->email,
                'sms', 'whatsapp' => omani_phone($customer->phone),
                default => null,
            };
            if (! $recipient || ! $this->channelEnabled($channel)) {
                continue;
            }
            $msg = $this->render($event, $channel, $data);
            if (! $msg) {
                continue;
            }
            $log = NotificationLog::create([
                'channel' => $channel, 'recipient' => $recipient, 'event' => $event, 'subject' => $msg['subject'],
                'body' => $msg['body'], 'status' => 'queued',
                'related_type' => $related?->getMorphClass(), 'related_id' => $related?->getKey(),
            ]);
            SendChannelMessage::dispatch($log->id, tenant()->id())->afterCommit();
        }
        // in-app notification for the portal user
        if ($customer->portalUser) {
            $msg = $this->render($event, 'database', $data) ?? ['subject' => __(self::EVENTS[$event] ?? $event), 'body' => $data['number'] ?? ''];
            $customer->portalUser->notify(new \App\Modules\Core\Notifications\SystemMessage($msg['subject'] ?: __(self::EVENTS[$event] ?? $event), $msg['body'], $data['link'] ?? null));
        }
    }

    public function channelEnabled(string $channel): bool
    {
        return match ($channel) {
            'mail' => (bool) config('mail.default') && config('mail.default') !== 'array',
            'sms' => (bool) IntegrationManager::active('sms'),
            'whatsapp' => (bool) IntegrationManager::active('whatsapp'),
            default => false,
        };
    }
}
