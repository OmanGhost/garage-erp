<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Services\AttendanceService;
use Illuminate\Http\Request;

/** The check-in / check-out button: the server decides which one from the employee's state. */
class AttendancePunchController extends Controller
{
    public function __invoke(Request $request, AttendanceService $svc)
    {
        abort_unless(setting('hr.self_attendance', true), 403);
        $employee = AttendanceService::employeeFor($request->user());
        abort_unless($employee, 403, __('Your user is not linked to an active employee.'));
        $r = $svc->punch($employee, $request->ip());
        $a = $r['attendance'];
        $msg = $r['action'] === 'in'
            ? ($a->late_minutes > 0 ? __('Checked in at :t — :m minutes late.', ['t' => $a->check_in->format('H:i'), 'm' => $a->late_minutes])
                : __('Checked in at :t. Have a good day!', ['t' => $a->check_in->format('H:i')]))
            : __('Checked out at :t. Worked :h.', ['t' => $a->check_out->format('H:i'), 'h' => intdiv($a->worked_minutes, 60).':'.str_pad((string) ($a->worked_minutes % 60), 2, '0', STR_PAD_LEFT)]);

        return back()->with('success', $msg);
    }
}
