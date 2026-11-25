<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Models\Payroll;
use App\Modules\HR\Models\PayrollItem;
use App\Modules\HR\Services\PayrollService;
use App\Support\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class HrController extends Controller
{
    public function __construct(protected PayrollService $payroll) {}

    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    public function daily(Request $request)
    {
        $this->can('attendance.create');
        $date = $request->query('date', now()->toDateString());
        $employees = Employee::query()->where('status', 'active')->orderBy('name')->get();
        $existing = Attendance::query()->whereDate('date', $date)->get()->keyBy('employee_id');

        return view('hr::daily', compact('date', 'employees', 'existing'));
    }

    public function saveDaily(Request $request)
    {
        $this->can('attendance.create');
        $data = $request->validate(['date' => ['required', 'date'], 'rows' => ['array'], 'rows.*.status' => ['required', 'in:present,late,absent,leave,holiday,weekend'],
            'rows.*.in' => ['nullable', 'date_format:H:i'], 'rows.*.out' => ['nullable', 'date_format:H:i']]);
        $std = (int) setting('hr.working_minutes_per_day', 480);
        foreach ($data['rows'] ?? [] as $employeeId => $r) {
            if (! Employee::query()->whereKey($employeeId)->exists()) {
                continue;
            }
            $in = ! empty($r['in']) ? Carbon::parse($data['date'].' '.$r['in']) : null;
            $out = ! empty($r['out']) ? Carbon::parse($data['date'].' '.$r['out']) : null;
            $worked = $in && $out && $out->gt($in) ? (int) $in->diffInMinutes($out) : 0;
            Attendance::query()->updateOrCreate(['employee_id' => $employeeId, 'date' => $data['date']], [
                'status' => $r['status'], 'check_in' => $in, 'check_out' => $out, 'worked_minutes' => $worked,
                'overtime_minutes' => max(0, $worked - $std), 'source' => 'manual',
            ]);
        }

        return back()->with('success', __('Attendance saved.'));
    }

    public function decideLeave(LeaveRequest $leave, string $decision)
    {
        $this->can('leaves.approve');
        $this->payroll->decideLeave($leave, $decision === 'approve');

        return back()->with('success', __('Leave request updated.'));
    }

    public function createLoan()
    {
        $this->can('loans.create');

        return view('hr::loan-form', ['banks' => Options::bankAccounts()]);
    }

    public function storeLoan(Request $request)
    {
        $this->can('loans.create');
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('company_id', tenant()->id())],
            'amount' => ['required', 'numeric', 'gt:0'], 'installment' => ['required', 'numeric', 'gt:0', 'lte:amount'],
            'bank_account_id' => ['required', 'integer', Rule::exists('bank_accounts', 'id')->where('company_id', tenant()->id())], 'notes' => ['nullable', 'string', 'max:250'],
        ]);
        $this->payroll->giveLoan(Employee::query()->findOrFail($data['employee_id']), $data['amount'], $data['installment'], $data['bank_account_id'], $data['notes'] ?? null);

        return redirect()->route('crud.index', 'employee-loans')->with('success', __('Advance recorded and paid.'));
    }

    public function createPayroll()
    {
        $this->can('payroll.create');

        return view('hr::payroll-form', ['branches' => Options::branches()]);
    }

    public function generatePayroll(Request $request)
    {
        $this->can('payroll.create');
        $data = $request->validate(['month' => ['required', 'date_format:Y-m'], 'branch_id' => ['nullable', 'integer']]);
        $p = $this->payroll->generate(Carbon::createFromFormat('Y-m', $data['month']), $data['branch_id'] ?? null);

        return redirect()->route('hr.payroll.show', $p)->with('success', __('Payroll calculated.'));
    }

    public function payroll(Payroll $payroll)
    {
        $this->can('payroll.view');

        return view('hr::payroll', ['p' => $payroll->load('items.employee', 'approver'), 'banks' => Options::bankAccounts('bank') ?: Options::bankAccounts()]);
    }

    public function payrollAction(Request $request, Payroll $payroll, string $action)
    {
        match ($action) {
            'approve' => [$this->can('payroll.approve'), $this->payroll->approve($payroll)],
            'post' => [$this->can('payroll.approve'), $this->payroll->post($payroll)],
            'pay' => [$this->can('payroll.approve'), $this->payroll->pay($payroll, $request->validate(['bank_account_id' => ['required', 'integer']])['bank_account_id'])],
        };

        return back()->with('success', __('Payroll updated.'));
    }

    public function payslip(Payroll $payroll, PayrollItem $item)
    {
        $this->can('payroll.print');
        abort_unless($item->payroll_id === $payroll->id, 404);

        return view('hr::payslip', ['p' => $payroll, 'i' => $item->load('employee.jobTitle', 'employee.department')]);
    }
}
