<?php

namespace App\Modules\HR\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeAdjustment;
use App\Modules\HR\Models\EmployeeLoan;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Models\Payroll;
use App\Modules\Workshop\Models\Commission;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Attendance, leave and monthly payroll:
 *   gross = basic + allowances + overtime + commissions + bonuses
 *   net   = gross - deductions - loan installments - unpaid absence
 *   post: Dr Salaries expense / Dr Commission expense   Cr Salaries payable, Cr Employee advances (loans)
 *   pay:  Dr Salaries payable  Cr Bank
 */
class PayrollService
{
    public function __construct(protected PostingService $posting) {}

    public function checkIn(Employee $e, ?Carbon $at = null, string $source = 'manual'): Attendance
    {
        $at ??= now();

        return Attendance::query()->firstOrCreate(['employee_id' => $e->id, 'date' => $at->toDateString()],
            ['check_in' => $at, 'status' => 'present', 'source' => $source]);
    }

    public function checkOut(Employee $e, ?Carbon $at = null, string $source = 'manual'): Attendance
    {
        $at ??= now();
        $a = Attendance::query()->where('employee_id', $e->id)->whereDate('date', $at->toDateString())->first()
            ?? $this->checkIn($e, $at->copy()->startOfDay()->addHours(8), $source);
        $worked = $a->check_in ? max(0, (int) round($a->check_in->diffInMinutes($at))) : 0;
        $standard = (int) Settings::get('hr.working_minutes_per_day', 480);
        $a->update(['check_out' => $at, 'worked_minutes' => $worked, 'overtime_minutes' => max(0, $worked - $standard), 'source' => $source]);

        return $a;
    }

    public function decideLeave(LeaveRequest $l, bool $approve): void
    {
        DB::transaction(function () use ($l, $approve) {
            $l->update(['status' => $approve ? 'approved' : 'rejected', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            if ($approve) {
                for ($d = $l->start_date->copy(); $d->lte($l->end_date); $d->addDay()) {
                    Attendance::query()->updateOrCreate(['employee_id' => $l->employee_id, 'date' => $d->toDateString()], ['status' => 'leave', 'notes' => $l->type?->name]);
                }
            }
        });
    }

    public function generate(Carbon $month, ?int $branchId = null): Payroll
    {
        $month = $month->copy()->startOfMonth();
        if (Payroll::query()->whereDate('period_month', $month)->where('branch_id', $branchId)->where('status', '!=', 'draft')->exists()) {
            throw new BusinessException(__('Payroll for this month is already approved.'));
        }

        return DB::transaction(function () use ($month, $branchId) {
            Payroll::query()->whereDate('period_month', $month)->where('branch_id', $branchId)->where('status', 'draft')->get()->each(fn ($p) => $p->delete());
            $payroll = Payroll::create(['number' => DocumentNumber::next('payroll'), 'period_month' => $month->toDateString(), 'branch_id' => $branchId, 'status' => 'draft']);
            $end = $month->copy()->endOfMonth();
            $days = $month->daysInMonth;
            $otRate = Settings::get('hr.overtime_rate', '1.25');
            $minutesPerDay = (int) Settings::get('hr.working_minutes_per_day', 480);
            $employees = Employee::query()->where('status', '!=', 'terminated')->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where(fn ($q) => $q->whereNull('hire_date')->orWhere('hire_date', '<=', $end))->get();
            $tg = $td = $tn = '0';
            foreach ($employees as $e) {
                $att = Attendance::query()->where('employee_id', $e->id)->whereBetween('date', [$month->toDateString(), $end->toDateString()])->get();
                $unpaidLeaveDays = LeaveRequest::query()->where('employee_id', $e->id)->where('status', 'approved')
                    ->whereHas('type', fn ($q) => $q->where('is_paid', false))
                    ->where('start_date', '<=', $end)->where('end_date', '>=', $month)->sum('days');
                $absent = $att->where('status', 'absent')->count() + (float) $unpaidLeaveDays;
                $dailyRate = Money::div($e->basic_salary, $days, 6);
                $hourly = Money::div($dailyRate, $minutesPerDay / 60, 6);
                $overtimeMinutes = (int) $att->sum('overtime_minutes');
                $overtime = Money::mul(Money::mul(Money::div($overtimeMinutes, 60, 6), $hourly, 6), $otRate);
                $allowances = Money::add($e->housing_allowance, $e->transport_allowance, $e->other_allowances);
                $adj = EmployeeAdjustment::query()->where('employee_id', $e->id)->whereNull('payroll_id')->whereBetween('date', [$month->toDateString(), $end->toDateString()])->get();
                $bonuses = Money::sum($adj->whereIn('type', ['bonus', 'incentive', 'allowance', 'overtime'])->pluck('amount'));
                $deductions = Money::sum($adj->whereIn('type', ['deduction', 'penalty'])->pluck('amount'));
                $commissions = $e->user_id ? Money::sum(Commission::query()->where('user_id', $e->user_id)->where('status', 'pending')->whereNull('payroll_id')
                    ->where('earned_at', '<=', $end)->pluck('amount')) : '0';
                $loanDed = '0';
                foreach (EmployeeLoan::query()->where('employee_id', $e->id)->where('status', 'active')->get() as $loan) {
                    $loanDed = Money::add($loanDed, Money::min($loan->installment, $loan->remaining));
                }
                $absenceDed = Money::mul($dailyRate, $absent);
                $gross = Money::add($e->basic_salary, $allowances, $overtime, $commissions, $bonuses);
                $totalDed = Money::add($deductions, $loanDed, $absenceDed);
                $net = Money::sub($gross, $totalDed);
                $payroll->items()->create([
                    'employee_id' => $e->id, 'basic' => $e->basic_salary, 'allowances' => $allowances, 'overtime' => $overtime,
                    'commissions' => $commissions, 'bonuses' => $bonuses, 'deductions' => $deductions, 'loan_deduction' => $loanDed,
                    'absence_deduction' => $absenceDed, 'gross' => $gross, 'net' => $net,
                    'worked_days' => $att->whereIn('status', ['present', 'late'])->count(), 'overtime_minutes' => $overtimeMinutes,
                ]);
                $tg = Money::add($tg, $gross);
                $td = Money::add($td, $totalDed);
                $tn = Money::add($tn, $net);
            }
            $payroll->update(['total_gross' => $tg, 'total_deductions' => $td, 'total_net' => $tn]);

            return $payroll;
        });
    }

    public function approve(Payroll $p): void
    {
        if ($p->status !== 'draft') {
            throw new BusinessException(__('Only draft payrolls can be approved.'));
        }
        $p->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
    }

    public function post(Payroll $p): void
    {
        DB::transaction(function () use ($p) {
            $p = Payroll::query()->whereKey($p->id)->lockForUpdate()->firstOrFail();
            if ($p->status !== 'approved') {
                throw new BusinessException(__('Approve the payroll before posting.'));
            }
            $items = $p->items()->with('employee')->get();
            $salaries = Money::sum($items->map(fn ($i) => Money::sub(Money::sub($i->gross, $i->commissions), Money::add($i->deductions, $i->absence_deduction))));
            $commissions = Money::sum($items->pluck('commissions'));
            $loans = Money::sum($items->pluck('loan_deduction'));
            $net = Money::sum($items->pluck('net'));
            $end = $p->period_month->copy()->endOfMonth();
            $je = $this->posting->post([
                'date' => $end->toDateString(), 'description' => __('Payroll').' '.$p->period_month->format('Y-m'), 'source' => $p,
                'lines' => [
                    ['account' => 'salaries_expense', 'debit' => $salaries],
                    ['account' => 'commission_expense', 'debit' => $commissions],
                    ['account' => 'employee_advances', 'credit' => $loans],
                    ['account' => 'salaries_payable', 'credit' => $net],
                ],
            ]);
            foreach ($items as $i) {
                if ($i->employee->user_id) {
                    Commission::query()->where('user_id', $i->employee->user_id)->where('status', 'pending')->whereNull('payroll_id')
                        ->where('earned_at', '<=', $end)->update(['status' => 'paid', 'payroll_id' => $p->id]);
                }
                EmployeeAdjustment::query()->where('employee_id', $i->employee_id)->whereNull('payroll_id')
                    ->whereBetween('date', [$p->period_month->toDateString(), $end->toDateString()])->update(['payroll_id' => $p->id]);
                $left = $i->loan_deduction;
                foreach (EmployeeLoan::query()->where('employee_id', $i->employee_id)->where('status', 'active')->orderBy('date')->get() as $loan) {
                    if (! Money::isPositive($left)) {
                        break;
                    }
                    $take = Money::min($left, Money::min($loan->installment, $loan->remaining));
                    $remaining = Money::sub($loan->remaining, $take);
                    $loan->update(['remaining' => $remaining, 'status' => Money::isPositive($remaining) ? 'active' : 'settled']);
                    $left = Money::sub($left, $take);
                }
            }
            $p->forceFill(['status' => 'posted', 'posted_at' => now(), 'journal_entry_id' => $je->id])->save();
            AuditLogger::log('payroll.posted', $p, ['net' => $net]);
        });
    }

    public function pay(Payroll $p, int $bankAccountId): void
    {
        DB::transaction(function () use ($p, $bankAccountId) {
            if ($p->status !== 'posted') {
                throw new BusinessException(__('Post the payroll before paying.'));
            }
            $bank = BankAccount::query()->findOrFail($bankAccountId);
            $this->posting->post([
                'date' => now()->toDateString(), 'description' => __('Salaries payment').' '.$p->period_month->format('Y-m'),
                'source' => $p, 'purpose' => 'payment',
                'lines' => [['account' => 'salaries_payable', 'debit' => $p->total_net], ['account' => $bank->account_id, 'credit' => $p->total_net]],
            ]);
            $p->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        });
    }

    /** Give a salary advance (loan): Dr Employee advances, Cr Cash. */
    public function giveLoan(Employee $e, mixed $amount, mixed $installment, int $bankAccountId, ?string $notes = null): EmployeeLoan
    {
        return DB::transaction(function () use ($e, $amount, $installment, $bankAccountId, $notes) {
            $loan = EmployeeLoan::create(['employee_id' => $e->id, 'date' => now()->toDateString(), 'amount' => $amount,
                'installment' => $installment, 'remaining' => $amount, 'status' => 'active', 'notes' => $notes]);
            $je = $this->posting->post([
                'date' => now()->toDateString(), 'description' => __('Salary advance').' - '.$e->name, 'source' => $loan,
                'lines' => [['account' => 'employee_advances', 'debit' => $amount, 'party' => $e],
                    ['account' => BankAccount::query()->findOrFail($bankAccountId)->account_id, 'credit' => $amount]],
            ]);
            $loan->update(['journal_entry_id' => $je->id]);

            return $loan;
        });
    }

    /** Technician productivity: billed vs. attended hours. */
    public function productivity(string $from, string $to)
    {
        return DB::table('employees as e')->where('e.company_id', tenant()->id())->where('e.is_technician', true)
            ->leftJoin('job_card_tasks as t', function ($j) use ($from, $to) {
                $j->on('t.technician_id', '=', 'e.user_id')->where('t.status', 'done')->whereBetween('t.finished_at', [$from, $to.' 23:59:59']);
            })
            ->groupBy('e.id', 'e.name')
            ->selectRaw('e.id, e.name, COUNT(t.id) tasks, COALESCE(SUM(t.actual_minutes),0) actual_minutes, COALESCE(SUM(t.estimated_minutes),0) estimated_minutes, COALESCE(SUM(t.line_total),0) billed')
            ->get()->map(function ($r) use ($from, $to) {
                $att = DB::table('attendances')->where('employee_id', $r->id)->whereBetween('date', [$from, $to])->sum('worked_minutes');
                $r->attended_minutes = (int) $att;
                $r->utilization = $att > 0 ? round($r->actual_minutes * 100 / $att, 1) : null;
                $r->efficiency = $r->actual_minutes > 0 ? round($r->estimated_minutes * 100 / $r->actual_minutes, 1) : null;

                return $r;
            });
    }
}
