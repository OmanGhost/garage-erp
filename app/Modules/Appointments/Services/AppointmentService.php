<?php

namespace App\Modules\Appointments\Services;

use App\Exceptions\BusinessException;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Holiday;
use App\Modules\Core\Models\WorkingHour;
use App\Modules\Core\Services\NotificationService;
use App\Modules\Workshop\Models\Department;
use App\Support\DocumentNumber;
use Illuminate\Support\Carbon;

/**
 * Booking with capacity checks: working hours, holidays, branch daily capacity,
 * department concurrent capacity, technician and bay double-booking.
 */
class AppointmentService
{
    public function book(array $data, bool $notify = true): Appointment
    {
        $start = Carbon::parse($data['starts_at']);
        $end = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : $start->copy()->addMinutes((int) ($data['duration'] ?? 60));
        $this->assertAvailable($start, $end, $data);
        $a = Appointment::create([
            'number' => DocumentNumber::next('appointment'),
            'customer_id' => $data['customer_id'], 'vehicle_id' => $data['vehicle_id'] ?? null,
            'department_id' => $data['department_id'] ?? null, 'service_id' => $data['service_id'] ?? null,
            'technician_id' => $data['technician_id'] ?? null, 'bay_id' => $data['bay_id'] ?? null,
            'starts_at' => $start, 'ends_at' => $end, 'status' => $data['status'] ?? 'scheduled',
            'source' => $data['source'] ?? 'staff', 'notes' => $data['notes'] ?? null,
            'branch_id' => $data['branch_id'] ?? tenant()->branchId() ?? Branch::query()->value('id'), 'created_by' => auth()->id(),
        ]);
        if ($notify) {
            app(NotificationService::class)->notifyCustomer($a->customer, 'appointment.booked', [
                'number' => $a->number, 'date' => $start->format('Y-m-d'), 'time' => $start->format('H:i'),
            ], [$a->customer->preferred_channel ?: 'sms'], $a);
        }

        return $a;
    }

    public function reschedule(Appointment $a, string $startsAt, ?string $endsAt = null): void
    {
        if (in_array($a->status, ['completed', 'cancelled', 'checked_in'], true)) {
            throw new BusinessException(__('This appointment cannot be rescheduled.'));
        }
        $start = Carbon::parse($startsAt);
        $end = $endsAt ? Carbon::parse($endsAt) : $start->copy()->addMinutes($a->starts_at->diffInMinutes($a->ends_at));
        $this->assertAvailable($start, $end, $a->only(['department_id', 'technician_id', 'bay_id', 'branch_id']), $a->id);
        $a->update(['starts_at' => $start, 'ends_at' => $end, 'rescheduled_count' => $a->rescheduled_count + 1, 'status' => 'scheduled']);
    }

    public function assertAvailable(Carbon $start, Carbon $end, array $data, ?int $ignoreId = null): void
    {
        if ($end->lte($start)) {
            throw new BusinessException(__('End time must be after start time.'));
        }
        if ($start->lt(now()->subMinutes(5)) && ($data['source'] ?? 'staff') === 'portal') {
            throw new BusinessException(__('Cannot book in the past.'));
        }
        $branchId = $data['branch_id'] ?? tenant()->branchId();
        $wh = WorkingHour::query()->where('day_of_week', $start->dayOfWeek)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))->orderByDesc('branch_id')->first();
        if ($wh && ($wh->is_closed || ($wh->opens_at && $start->format('H:i:s') < $wh->opens_at) || ($wh->closes_at && $end->format('H:i:s') > $wh->closes_at))) {
            throw new BusinessException(__('The workshop is closed at the selected time.'));
        }
        $holiday = Holiday::query()->where(function ($q) use ($start) {
            $d = $start->toDateString();
            $q->whereDate('date', '<=', $d)->where(fn ($w) => $w->whereDate('end_date', '>=', $d)->orWhere(fn ($x) => $x->whereNull('end_date')->whereDate('date', $d)));
        })->first();
        if ($holiday) {
            throw new BusinessException(__('The selected date is a holiday (:h).', ['h' => $holiday->name]));
        }
        $overlap = fn () => Appointment::query()->whereNotIn('status', ['cancelled', 'no_show', 'completed'])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start);

        if (! empty($data['technician_id']) && $overlap()->where('technician_id', $data['technician_id'])->exists()) {
            throw new BusinessException(__('The technician is already booked at this time.'));
        }
        if (! empty($data['bay_id']) && $overlap()->where('bay_id', $data['bay_id'])->exists()) {
            throw new BusinessException(__('The workshop bay is already booked at this time.'));
        }
        if (! empty($data['department_id'])) {
            $cap = Department::query()->find($data['department_id'])?->capacity ?? 0;
            if ($cap > 0 && $overlap()->where('department_id', $data['department_id'])->count() >= $cap) {
                throw new BusinessException(__('The department is fully booked at this time.'));
            }
        }
        $daily = Branch::query()->find($branchId)?->daily_capacity;
        if ($daily && Appointment::query()->whereNotIn('status', ['cancelled', 'no_show'])->where('branch_id', $branchId)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->whereDate('starts_at', $start->toDateString())->count() >= $daily) {
            throw new BusinessException(__('The daily workshop capacity is reached for this date.'));
        }
    }

    /** Free slots of $minutes for a date (for the portal booking form). */
    public function freeSlots(string $date, int $minutes = 60, ?int $departmentId = null): array
    {
        $day = Carbon::parse($date);
        $wh = WorkingHour::query()->where('day_of_week', $day->dayOfWeek)->orderByDesc('branch_id')->first();
        if (! $wh || $wh->is_closed) {
            return [];
        }
        $slots = [];
        $t = Carbon::parse($date.' '.($wh->opens_at ?? '08:00'));
        $close = Carbon::parse($date.' '.($wh->closes_at ?? '18:00'));
        while ($t->copy()->addMinutes($minutes)->lte($close)) {
            try {
                $this->assertAvailable($t->copy(), $t->copy()->addMinutes($minutes), ['department_id' => $departmentId]);
                if ($t->isFuture()) {
                    $slots[] = $t->format('H:i');
                }
            } catch (BusinessException) {
            }
            $t->addMinutes(30);
        }

        return $slots;
    }

    public function markNoShows(): int
    {
        return Appointment::query()->whereIn('status', ['scheduled', 'confirmed'])->where('ends_at', '<', now()->subHours(2))->update(['status' => 'no_show']);
    }
}
