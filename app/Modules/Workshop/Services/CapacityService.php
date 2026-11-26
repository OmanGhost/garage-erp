<?php

namespace App\Modules\Workshop\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Core\Models\Holiday;
use App\Modules\Core\Models\WorkingHour;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\JobCardTask;
use App\Modules\Workshop\Models\WorkshopBay;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Workshop resource planning: technicians and bays (lifts, pits…) are booked per task. Tasks are scheduled
 * automatically into the earliest free slot within working hours (no double booking of a technician or a bay,
 * appointments included), the job card gets an estimated completion time, and occupancy / productivity are
 * measured against the available hours.
 */
class CapacityService
{
    /** in-memory bookings made during one auto-schedule run: ['tech:5' => [[start, end], ...], 'bay:2' => ...] */
    protected array $planned = [];

    protected array $busyCache = [];

    /* ---------------- resources & calendar ---------------- */

    /** Active technicians of the company (users linked to an employee flagged as technician). */
    public function technicians(): Collection
    {
        return User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->where('is_active', true)
            ->whereHas('employee', fn ($e) => $e->where('is_technician', true))->orderBy('name')->get();
    }

    public function bays(): Collection
    {
        return WorkshopBay::query()->where('is_active', true)->orderBy('sort')->orderBy('code')->get();
    }

    /** Opening window of a day, or null when closed / holiday. @return array{0: Carbon, 1: Carbon}|null */
    public function window(Carbon $day, ?int $branchId = null): ?array
    {
        $branchId ??= tenant()->branchId();
        $wh = WorkingHour::query()->where('day_of_week', $day->dayOfWeek)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))->orderByDesc('branch_id')->first();
        if ($wh && $wh->is_closed) {
            return null;
        }
        $d = $day->toDateString();
        if (Holiday::query()->whereDate('date', '<=', $d)->where(fn ($w) => $w->whereDate('end_date', '>=', $d)->orWhere(fn ($x) => $x->whereNull('end_date')->whereDate('date', $d)))->exists()) {
            return null;
        }
        $open = Carbon::parse($d.' '.($wh?->opens_at ?? '08:00:00'));
        $close = Carbon::parse($d.' '.($wh?->closes_at ?? '18:00:00'));

        return $close->gt($open) ? [$open, $close] : null;
    }

    protected function onLeave(int $userId, Carbon $day): bool
    {
        return DB::table('leave_requests as l')->join('employees as e', 'e.id', '=', 'l.employee_id')
            ->where('e.user_id', $userId)->where('l.status', 'approved')
            ->whereDate('l.start_date', '<=', $day->toDateString())->whereDate('l.end_date', '>=', $day->toDateString())->exists();
    }

    /* ---------------- bookings ---------------- */

    /** Scheduled tasks that overlap [start, end) for a technician or a bay. */
    protected function taskOverlaps(string $column, int $id, Carbon $start, Carbon $end, ?int $ignoreTaskId = null)
    {
        return JobCardTask::query()->whereHas('jobCard', fn ($j) => $j->whereNotIn('status', [JobCard::CANCELLED, JobCard::DELIVERED, JobCard::COMPLETED]))
            ->where($column, $id)->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('scheduled_start')->where('scheduled_start', '<', $end)->where('scheduled_end', '>', $start)
            ->when($ignoreTaskId, fn ($q) => $q->whereKeyNot($ignoreTaskId));
    }

    protected function appointmentOverlaps(string $column, int $id, Carbon $start, Carbon $end)
    {
        return Appointment::query()->whereNotIn('status', ['cancelled', 'no_show', 'completed', 'checked_in'])
            ->where($column, $id)->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    /** Throws when the technician or the bay is already booked (tasks and appointments). */
    public function assertFree(?int $technicianId, ?int $bayId, Carbon $start, Carbon $end, ?int $ignoreTaskId = null, bool $checkAppointments = true): void
    {
        if ($end->lte($start)) {
            throw new BusinessException(__('End time must be after start time.'));
        }
        if ($technicianId && ($this->taskOverlaps('technician_id', $technicianId, $start, $end, $ignoreTaskId)->exists()
            || ($checkAppointments && $this->appointmentOverlaps('technician_id', $technicianId, $start, $end)->exists()))) {
            throw new BusinessException(__('The technician is already booked at this time.'));
        }
        if ($bayId && ($this->taskOverlaps('bay_id', $bayId, $start, $end, $ignoreTaskId)->exists()
            || ($checkAppointments && $this->appointmentOverlaps('bay_id', $bayId, $start, $end)->exists()))) {
            throw new BusinessException(__('The workshop bay is already booked at this time.'));
        }
    }

    /** Used by the appointment booking: appointments must not clash with scheduled job-card tasks. */
    public function assertNoTaskClash(?int $technicianId, ?int $bayId, Carbon $start, Carbon $end): void
    {
        $this->assertFree($technicianId, $bayId, $start, $end, null, false);
    }

    /** Busy intervals of a resource on one day (tasks + appointments + this run's plan). @return array<int, array{0: Carbon, 1: Carbon}> */
    protected function busy(string $kind, int $id, Carbon $day, ?int $ignoreJobCardId = null): array
    {
        $key = $kind.':'.$id.':'.$day->toDateString().':'.$ignoreJobCardId;
        if (! isset($this->busyCache[$key])) {
            $from = $day->copy()->startOfDay();
            $to = $day->copy()->endOfDay();
            $col = $kind === 'tech' ? 'technician_id' : 'bay_id';
            $list = [];
            foreach ($this->taskOverlaps($col, $id, $from, $to)->when($ignoreJobCardId, fn ($q) => $q->where('job_card_id', '!=', $ignoreJobCardId))->get(['scheduled_start', 'scheduled_end']) as $t) {
                $list[] = [$t->scheduled_start->copy(), $t->scheduled_end->copy()];
            }
            foreach ($this->appointmentOverlaps($col, $id, $from, $to)->get(['starts_at', 'ends_at']) as $a) {
                $list[] = [$a->starts_at->copy(), $a->ends_at->copy()];
            }
            $this->busyCache[$key] = $list;
        }

        return array_merge($this->busyCache[$key], $this->planned[$kind.':'.$id] ?? []);
    }

    /** Earliest start >= $from on resources ($techId, $bayId) for $minutes, searching $days ahead. */
    public function earliestSlot(?int $techId, ?int $bayId, Carbon $from, int $minutes, int $days = 14, ?int $ignoreJobCardId = null): ?Carbon
    {
        $day = $from->copy()->startOfDay();
        for ($i = 0; $i < $days; $i++, $day->addDay()) {
            $win = $this->window($day);
            if (! $win || ($techId && $this->onLeave($techId, $day))) {
                continue;
            }
            [$open, $close] = $win;
            $busy = array_merge($techId ? $this->busy('tech', $techId, $day, $ignoreJobCardId) : [], $bayId ? $this->busy('bay', $bayId, $day, $ignoreJobCardId) : []);
            usort($busy, fn ($a, $b) => $a[0] <=> $b[0]);
            $t = $from->gt($open) ? $from->copy() : $open->copy();
            $t = $this->roundUp($t);
            $long = $minutes > $open->diffInMinutes($close);
            while (true) {
                $end = $t->copy()->addMinutes($minutes);
                if (! $long && $end->gt($close)) {
                    break;
                }
                if ($long && $t->gt($open)) {
                    break; // a job longer than a day starts at opening time
                }
                $clash = null;
                foreach ($busy as [$bs, $be]) {
                    if ($bs->lt($end) && $be->gt($t)) {
                        $clash = $be;
                        break;
                    }
                }
                if (! $clash) {
                    return $t;
                }
                $t = $this->roundUp($clash->copy());
            }
        }

        return null;
    }

    protected function roundUp(Carbon $t): Carbon
    {
        $t->second(0);
        $m = (int) $t->format('i');

        return $m % 5 ? $t->addMinutes(5 - $m % 5) : $t;
    }

    /* ---------------- scheduling ---------------- */

    /**
     * Schedule the job card's open tasks into the earliest free slots (sequentially: one vehicle is worked on one
     * task at a time). Fixed technicians / bays are kept; otherwise the technician and bay that finish earliest are
     * chosen. With $persist = false nothing is saved (used for the completion forecast).
     *
     * @return array<int, array{task: JobCardTask, technician_id: ?int, bay_id: ?int, start: Carbon, end: Carbon}>
     */
    public function autoSchedule(JobCard $jc, bool $persist = true, bool $replan = false): array
    {
        $this->planned = [];
        $this->busyCache = [];
        $tasks = $jc->tasks()->whereIn('status', ['pending', 'paused'])->where('approval_status', 'approved')
            ->when(! $replan, fn ($q) => $q->whereNull('scheduled_start'))->orderBy('sort')->orderBy('id')->get();
        $techs = $this->technicians()->pluck('id')->all();
        $bays = $this->bays();
        $now = $this->roundUp(now());
        // continue after what is already scheduled / running on this vehicle
        $cursor = $now->copy();
        $lastPlanned = $jc->tasks()->whereNotIn('status', ['done', 'cancelled'])->whereNotNull('scheduled_end')
            ->when($replan, fn ($q) => $q->whereNotIn('id', $tasks->pluck('id')))->max('scheduled_end');
        if ($lastPlanned && Carbon::parse($lastPlanned)->gt($cursor)) {
            $cursor = Carbon::parse($lastPlanned);
        }
        $plan = [];
        foreach ($tasks as $task) {
            $minutes = $task->plannedMinutes();
            $techOptions = $task->technician_id ? [$task->technician_id] : ($techs ?: [null]);
            $bayIds = $task->bay_id ? [$task->bay_id] : ($jc->bay_id ? [$jc->bay_id]
                : ($bays->isEmpty() ? [null] : $bays->sortBy(fn ($b) => $task->department_id && $b->department_id === $task->department_id ? 0 : 1)->pluck('id')->all()));
            $best = null;
            foreach ($techOptions as $tid) {
                foreach ($bayIds as $bid) {
                    $start = $this->earliestSlot($tid, $bid, $cursor, $minutes, 14, $replan ? $jc->id : null);
                    if ($start && (! $best || $start->lt($best['start']))) {
                        $best = ['task' => $task, 'technician_id' => $tid, 'bay_id' => $bid, 'start' => $start, 'end' => $start->copy()->addMinutes($minutes)];
                    }
                }
            }
            if (! $best) {
                continue;
            }
            $plan[] = $best;
            if ($best['technician_id']) {
                $this->planned['tech:'.$best['technician_id']][] = [$best['start'], $best['end']];
            }
            if ($best['bay_id']) {
                $this->planned['bay:'.$best['bay_id']][] = [$best['start'], $best['end']];
            }
            $cursor = $best['end']->copy();
        }
        if ($persist && $plan) {
            DB::transaction(function () use ($plan, $jc) {
                foreach ($plan as $p) {
                    $p['task']->update(['technician_id' => $p['technician_id'] ?? $p['task']->technician_id, 'bay_id' => $p['bay_id'],
                        'scheduled_start' => $p['start'], 'scheduled_end' => $p['end']]);
                }
                if (! $jc->bay_id && $plan[0]['bay_id']) {
                    $jc->bay_id = $plan[0]['bay_id'];
                }
                $jc->estimated_completion_at = $this->plannedEnd($jc);
                $jc->save();
                AuditLogger::log('workshop.auto_scheduled', $jc, ['tasks' => count($plan), 'eta' => $jc->estimated_completion_at?->format('Y-m-d H:i')]);
            });
        }

        return $plan;
    }

    /** Manual booking of one task with clash checks. */
    public function scheduleTask(JobCardTask $task, ?int $technicianId, ?int $bayId, Carbon $start): void
    {
        $end = $start->copy()->addMinutes($task->plannedMinutes());
        $this->assertFree($technicianId, $bayId, $start, $end, $task->id);
        $task->update(['technician_id' => $technicianId ?: $task->technician_id, 'bay_id' => $bayId, 'scheduled_start' => $start, 'scheduled_end' => $end]);
        $jc = $task->jobCard;
        $jc->update(['estimated_completion_at' => $this->plannedEnd($jc)]);
    }

    public function unschedule(JobCardTask $task): void
    {
        $task->update(['scheduled_start' => null, 'scheduled_end' => null]);
        $task->jobCard->update(['estimated_completion_at' => $this->plannedEnd($task->jobCard)]);
    }

    /** End of the last scheduled open task, or null. */
    public function plannedEnd(JobCard $jc): ?Carbon
    {
        $max = $jc->tasks()->whereNotIn('status', ['done', 'cancelled'])->max('scheduled_end');

        return $max ? Carbon::parse($max) : null;
    }

    /** Estimated completion: the planned end, or a dry-run of the scheduler for unscheduled work. */
    public function forecast(JobCard $jc): ?Carbon
    {
        $plan = $this->autoSchedule($jc, false);
        $ends = array_map(fn ($p) => $p['end'], $plan);
        if ($e = $this->plannedEnd($jc)) {
            $ends[] = $e;
        }

        return $ends ? collect($ends)->max() : null;
    }

    /* ---------------- reporting ---------------- */

    /** Bookings of a day for the planner grid. */
    public function day(Carbon $day): array
    {
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();
        $tasks = JobCardTask::query()->with('jobCard.vehicle', 'technician', 'bay')
            ->whereHas('jobCard', fn ($j) => $j->where('status', '!=', JobCard::CANCELLED))
            ->whereNotNull('scheduled_start')->where('scheduled_start', '<', $to)->where('scheduled_end', '>', $from)
            ->where('status', '!=', 'cancelled')->orderBy('scheduled_start')->get();
        $appointments = Appointment::query()->with('customer', 'vehicle')->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)->orderBy('starts_at')->get();

        return ['window' => $this->window($day) ?? [Carbon::parse($day->toDateString().' 08:00'), Carbon::parse($day->toDateString().' 18:00')],
            'open' => (bool) $this->window($day), 'tasks' => $tasks, 'appointments' => $appointments];
    }

    protected function overlapMinutes(Carbon $s, Carbon $e, Carbon $from, Carbon $to): int
    {
        $a = $s->gt($from) ? $s : $from;
        $b = $e->lt($to) ? $e : $to;

        return $b->gt($a) ? (int) $a->diffInMinutes($b) : 0;
    }

    /**
     * Occupancy of bays and productivity of technicians for a period.
     *
     * @return array{available: int, bays: array, technicians: array, occupancy: float, productivity: float, efficiency: float, completed: int, turnaround_hours: ?float, late: int}
     */
    public function utilization(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $windows = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if ($w = $this->window($d->copy())) {
                $windows[] = $w;
            }
        }
        $available = array_sum(array_map(fn ($w) => (int) $w[0]->diffInMinutes($w[1]), $windows));
        $tasks = JobCardTask::query()->whereHas('jobCard', fn ($j) => $j->where('status', '!=', JobCard::CANCELLED))
            ->where('status', '!=', 'cancelled')->whereNotNull('scheduled_start')
            ->where('scheduled_start', '<', $to)->where('scheduled_end', '>', $from)->get();
        $appts = Appointment::query()->whereNotIn('status', ['cancelled', 'no_show'])->whereNull('job_card_id')
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get();

        $bays = [];
        foreach ($this->bays() as $b) {
            $booked = $tasks->where('bay_id', $b->id)->sum(fn ($t) => $this->overlapMinutes($t->scheduled_start, $t->scheduled_end, $from, $to))
                + $appts->where('bay_id', $b->id)->sum(fn ($a) => $this->overlapMinutes($a->starts_at, $a->ends_at, $from, $to));
            $bays[] = ['bay' => $b, 'booked' => $booked, 'rate' => $available ? min(100, round($booked * 100 / $available, 1)) : 0];
        }

        $logs = DB::table('job_card_time_logs as l')->join('job_card_tasks as t', 't.id', '=', 'l.job_card_task_id')
            ->join('job_cards as j', 'j.id', '=', 't.job_card_id')->where('j.company_id', tenant()->id())
            ->whereBetween('l.started_at', [$from, $to])->groupBy('l.technician_id')->selectRaw('l.technician_id, SUM(l.minutes) m')->pluck('m', 'technician_id');
        $flat = DB::table('job_card_tasks as t')->join('job_cards as j', 'j.id', '=', 't.job_card_id')->where('j.company_id', tenant()->id())
            ->where('t.status', 'done')->whereBetween('t.finished_at', [$from, $to])->groupBy('t.technician_id')
            ->selectRaw('t.technician_id, SUM(t.estimated_minutes) e, COUNT(*) n')->get()->keyBy('technician_id');
        $techs = [];
        foreach ($this->technicians() as $u) {
            $leaveMin = 0;
            foreach ($windows as [$o, $c]) {
                if ($this->onLeave($u->id, $o)) {
                    $leaveMin += (int) $o->diffInMinutes($c);
                }
            }
            $avail = max(0, $available - $leaveMin);
            $booked = $tasks->where('technician_id', $u->id)->sum(fn ($t) => $this->overlapMinutes($t->scheduled_start, $t->scheduled_end, $from, $to));
            $worked = (int) ($logs[$u->id] ?? 0);
            $earned = (int) ($flat[$u->id]->e ?? 0);
            $techs[] = ['user' => $u, 'available' => $avail, 'booked' => $booked, 'worked' => $worked, 'earned' => $earned, 'tasks' => (int) ($flat[$u->id]->n ?? 0),
                'load' => $avail ? min(100, round($booked * 100 / $avail, 1)) : 0,
                'productivity' => $avail ? min(100, round($worked * 100 / $avail, 1)) : 0,
                'efficiency' => $worked ? round($earned * 100 / $worked, 1) : null];
        }

        $completed = JobCard::query()->whereNotNull('completed_at')->whereBetween('completed_at', [$from, $to])->get(['received_at', 'completed_at']);
        $totalBay = count($bays) * $available;
        $totalTech = array_sum(array_column($techs, 'available'));

        return [
            'available' => $available, 'bays' => $bays, 'technicians' => $techs,
            'occupancy' => $totalBay ? round(array_sum(array_column($bays, 'booked')) * 100 / $totalBay, 1) : 0,
            'productivity' => $totalTech ? round(array_sum(array_column($techs, 'worked')) * 100 / $totalTech, 1) : 0,
            'efficiency' => ($w = array_sum(array_column($techs, 'worked'))) ? round(array_sum(array_column($techs, 'earned')) * 100 / $w, 1) : 0,
            'completed' => $completed->count(),
            'turnaround_hours' => $completed->isNotEmpty() ? round($completed->avg(fn ($j) => $j->received_at->diffInMinutes($j->completed_at)) / 60, 1) : null,
            'late' => JobCard::query()->whereIn('status', array_diff(JobCard::OPEN_STATUSES, [JobCard::READY]))
                ->where(fn ($q) => $q->where('promised_at', '<', now())->orWhere(fn ($w) => $w->whereNull('promised_at')->where('estimated_completion_at', '<', now())))->count(),
        ];
    }
}
