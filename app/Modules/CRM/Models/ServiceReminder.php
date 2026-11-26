<?php

namespace App\Modules\CRM\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Periodic maintenance reminder. Tracking: every message the system sends and every manual contact (call,
 * WhatsApp, visit…) is a ReminderContact; the customer's confirmation is acknowledged_at; a booked visit links
 * the appointment.
 */
class ServiceReminder extends TenantModel
{
    protected $casts = [
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'last_contact_at' => 'datetime',
        'follow_up_on' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\ServicePackage::class, 'service_package_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Appointments\Models\Appointment::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ReminderContact::class)->latest('contacted_at')->latest('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(\App\Modules\Core\Models\NotificationLog::class, 'related_id')
            ->where('related_type', $this->getMorphClass())->latest('id');
    }

    /** Where the reminder stands for the customer: due | overdue | sent | delivered | failed | acknowledged | booked | done | cancelled */
    public function stage(): string
    {
        if (in_array($this->status, ['done', 'cancelled'], true)) {
            return $this->status;
        }
        if ($this->appointment_id) {
            return 'booked';
        }
        if ($this->acknowledged_at) {
            return 'acknowledged';
        }
        $last = $this->relationLoaded('messages') ? $this->messages->first() : $this->messages()->first();
        if ($last) {
            return match ($last->status) { 'sent' => 'delivered', 'failed' => 'failed', default => 'sent' };
        }
        if ($this->status === 'sent') {
            return 'sent';
        }

        return $this->due_date && $this->due_date->isPast() ? 'overdue' : 'due';
    }
}
