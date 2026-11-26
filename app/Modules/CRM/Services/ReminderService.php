<?php

namespace App\Modules\CRM\Services;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Core\Services\NotificationService;
use App\Modules\CRM\Models\ServiceReminder;
use App\Modules\Workshop\Models\JobCard;
use Illuminate\Support\Facades\URL;

/**
 * Periodic maintenance reminders (by date and/or odometer) and appointment reminders.
 */
class ReminderService
{
    public function scheduleAfterJob(JobCard $jc): void
    {
        $vehicle = $jc->vehicle;
        $interval = $vehicle->fleet?->maintenance_interval_days ?? 180;
        $km = $vehicle->fleet?->maintenance_interval_km ?? 5000;
        ServiceReminder::query()->where('vehicle_id', $vehicle->id)->where('status', 'scheduled')->update(['status' => 'cancelled']);
        ServiceReminder::create([
            'customer_id' => $jc->customer_id, 'vehicle_id' => $vehicle->id, 'title' => __('Periodic maintenance'),
            'due_date' => now()->addDays($interval)->toDateString(),
            'due_odometer' => ($jc->odometer_out ?? $jc->odometer_in ?? $vehicle->odometer) + $km,
            'status' => 'scheduled', 'last_job_card_id' => $jc->id,
        ]);
        $vehicle->forceFill(['next_service_date' => now()->addDays($interval), 'next_service_odometer' => ($jc->odometer_out ?? $vehicle->odometer) + $km])->save();
    }

    /** Send due service reminders (date within 7 days). Returns count sent. */
    public function sendDue(): int
    {
        $n = 0;
        foreach (ServiceReminder::query()->with('customer', 'vehicle')->where('status', 'scheduled')->whereDate('due_date', '<=', now()->addDays(7))->get() as $r) {
            if (! $r->customer || ! $r->customer->marketing_opt_in) {
                continue;
            }
            $this->send($r);
            $n++;
        }

        return $n;
    }

    /**
     * Send the reminder message now (automatic run or "Send" button) and record it in the reminder tracking.
     * Returns the number of messages queued (0 when no SMS / WhatsApp / e-mail channel is configured).
     */
    public function send(ServiceReminder $r, ?string $channel = null): int
    {
        $r->loadMissing('customer', 'vehicle');
        $logs = app(NotificationService::class)->notifyCustomer($r->customer, 'service.reminder', [
            'service' => $r->title, 'plate' => $r->vehicle?->plate(), 'link' => route('portal.appointments.create'),
        ], [$channel ?: ($r->customer->preferred_channel ?: 'sms')], $r);
        foreach ($logs as $log) {
            $r->contacts()->create([
                'channel' => $log->channel === 'mail' ? 'email' : $log->channel, 'outcome' => 'message_sent',
                'notification_log_id' => $log->id, 'user_id' => auth()->id(), 'contacted_at' => now(),
            ]);
        }
        $r->forceFill([
            'status' => $r->status === 'scheduled' ? 'sent' : $r->status, 'sent_at' => now(),
            'last_contact_at' => $logs ? now() : $r->last_contact_at, 'contact_attempts' => $r->contact_attempts + count($logs),
            'last_outcome' => $logs ? 'message_sent' : $r->last_outcome,
        ])->save();

        return count($logs);
    }

    /** Record a manual contact (call, WhatsApp, visit…) and its outcome; "acknowledged" / "booked" update the reminder. */
    public function logContact(ServiceReminder $r, string $channel, string $outcome, ?string $notes = null, ?string $followUpOn = null): void
    {
        $r->contacts()->create(['channel' => $channel, 'outcome' => $outcome, 'notes' => $notes, 'user_id' => auth()->id(), 'contacted_at' => now()]);
        $r->forceFill([
            'last_contact_at' => now(), 'contact_attempts' => $r->contact_attempts + 1, 'last_outcome' => $outcome,
            'follow_up_on' => $followUpOn,
            'acknowledged_at' => in_array($outcome, ['reached', 'acknowledged', 'booked'], true) ? ($r->acknowledged_at ?? now()) : $r->acknowledged_at,
            'status' => $outcome === 'declined' ? 'cancelled' : ($r->status === 'scheduled' ? 'sent' : $r->status),
        ])->save();
    }

    public function sendAppointmentReminders(): int
    {
        $n = 0;
        $svc = app(NotificationService::class);
        $list = Appointment::query()->with('customer')->whereIn('status', ['scheduled', 'confirmed'])->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addDay()])->get();
        foreach ($list as $a) {
            $svc->notifyCustomer($a->customer, 'appointment.reminder', [
                'number' => $a->number, 'date' => $a->starts_at->format('Y-m-d'), 'time' => $a->starts_at->format('H:i'),
            ], [$a->customer->preferred_channel ?: 'sms'], $a);
            $a->update(['reminder_sent_at' => now()]);
            $n++;
        }

        return $n;
    }
}
