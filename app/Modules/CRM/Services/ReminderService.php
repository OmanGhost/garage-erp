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
        $svc = app(NotificationService::class);
        foreach (ServiceReminder::query()->with('customer', 'vehicle')->where('status', 'scheduled')->whereDate('due_date', '<=', now()->addDays(7))->get() as $r) {
            if (! $r->customer || ! $r->customer->marketing_opt_in) {
                continue;
            }
            $svc->notifyCustomer($r->customer, 'service.reminder', [
                'service' => $r->title, 'plate' => $r->vehicle?->plate(), 'link' => route('portal.appointments.create'),
            ], [$r->customer->preferred_channel ?: 'sms'], $r);
            $r->update(['status' => 'sent', 'sent_at' => now()]);
            $n++;
        }

        return $n;
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
