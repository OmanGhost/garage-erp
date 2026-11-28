<?php

namespace App\Modules\HR\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use App\Modules\Workshop\Services\CapacityService;
use App\Support\Audit\AuditLogger;
use App\Support\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-click self attendance. The server decides what the click means from the employee's state:
 *   no open attendance → check in (lateness against the branch opening time + grace),
 *   open attendance (checked in within the last 16 hours, so night shifts work) → check out,
 *   already checked out today → refused.
 */
class AttendanceService
{
    public const OPEN_HOURS = 16;

    public static function employeeFor(?User $user): ?Employee
    {
        if (! $user || $user->customer_id) {
            return null;
        }

        return Employee::query()->where('user_id', $user->id)->where('status', 'active')->first();
    }

    /** The attendance still open (checked in, not out) for the employee, if any. */
    public function open(Employee $e, ?Carbon $at = null): ?Attendance
    {
        $at ??= now();

        return Attendance::query()->where('employee_id', $e->id)->whereNotNull('check_in')->whereNull('check_out')
            ->where('check_in', '>=', $at->copy()->subHours(self::OPEN_HOURS))->latest('check_in')->first();
    }

    /** @return array{state: string, attendance: ?Attendance} state: in | out | done */
    public function state(Employee $e): array
    {
        if ($open = $this->open($e)) {
            return ['state' => 'out', 'attendance' => $open];
        }
        $today = Attendance::query()->where('employee_id', $e->id)->whereDate('date', today())->first();

        return ['state' => $today && $today->check_out ? 'done' : 'in', 'attendance' => $today];
    }

    /** @return array{action: string, attendance: Attendance} */
    public function punch(Employee $e, ?string $ip = null, ?Carbon $at = null): array
    {
        $at ??= now();
        try {
            return DB::transaction(function () use ($e, $ip, $at) {
                Employee::query()->whereKey($e->id)->lockForUpdate()->first();
                if ($open = $this->open($e, $at)) {
                    if ($open->check_in->diffInSeconds($at) < 120) {
                        throw new BusinessException(__('You checked in less than two minutes ago.'));
                    }
                    $worked = (int) round($open->check_in->diffInMinutes($at));
                    $standard = (int) Settings::get('hr.working_minutes_per_day', 480);
                    $open->update(['check_out' => $at, 'check_out_ip' => $ip, 'worked_minutes' => $worked,
                        'overtime_minutes' => max(0, $worked - $standard)]);
                    AuditLogger::log('attendance.check_out', $open, ['ip' => $ip, 'worked' => $worked]);

                    return ['action' => 'out', 'attendance' => $open];
                }
                $existing = Attendance::query()->where('employee_id', $e->id)->whereDate('date', $at->toDateString())->first();
                if ($existing && $existing->check_out) {
                    throw new BusinessException(__('You have already checked out today.'));
                }
                $late = $this->lateMinutes($e, $at);
                $data = ['check_in' => $at, 'check_in_ip' => $ip, 'status' => $late > 0 ? 'late' : 'present', 'late_minutes' => $late, 'source' => 'self'];
                $a = $existing ? tap($existing)->update($data) : Attendance::create(['employee_id' => $e->id, 'date' => $at->toDateString()] + $data);
                AuditLogger::log('attendance.check_in', $a, ['ip' => $ip, 'late' => $late]);

                return ['action' => 'in', 'attendance' => $a];
            });
        } catch (QueryException $ex) {
            // two clicks at the same moment: the unique (employee, date) row already exists
            throw new BusinessException(__('Attendance was just recorded. Refresh the page.'));
        }
    }

    /** Minutes after the branch opening time (plus grace); closed days and holidays have no lateness. */
    public function lateMinutes(Employee $e, Carbon $at): int
    {
        $window = app(CapacityService::class)->window($at->copy(), $e->branch_id);
        if (! $window) {
            return 0;
        }
        $grace = (int) Settings::get('hr.late_grace_minutes', 10);
        $limit = $window[0]->copy()->addMinutes($grace);

        return $at->gt($limit) ? (int) $window[0]->diffInMinutes($at) : 0;
    }
}
