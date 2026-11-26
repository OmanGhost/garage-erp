<?php

namespace App\Modules\CRM\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A contact attempt for a service reminder (message sent by the system, call, WhatsApp, visit…) and its outcome. */
class ReminderContact extends TenantModel
{
    public const CHANNELS = ['call', 'whatsapp', 'sms', 'email', 'visit', 'system'];

    public const OUTCOMES = ['message_sent', 'reached', 'acknowledged', 'booked', 'no_answer', 'declined', 'wrong_number', 'failed'];

    protected $casts = ['contacted_at' => 'datetime'];

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(ServiceReminder::class, 'service_reminder_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function notificationLog(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\NotificationLog::class);
    }
}
