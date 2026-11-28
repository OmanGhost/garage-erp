<?php

namespace App\Modules\HR\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeLoan;
use App\Modules\HR\Models\EmployeeLoanPayment;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Salary advances and internal financing for employees.
 *  - advance:   no profit; repaid by a monthly installment.
 *  - financing: flat profit = principal × rate %, total payable = principal + profit, split into N monthly installments.
 * Disbursement: Dr Employee advances (principal only) / Cr Bank. Every collection (payroll deduction or cash) is split
 * into principal (Cr Employee advances) and profit (Cr Financing income); the profit share is computed cumulatively so
 * rounding can never leave profit uncollected. `remaining` is the total payable still outstanding (profit included).
 */
class LoanService
{
    public function __construct(protected PostingService $posting) {}

    /** Installment amount: total / months rounded up to the fils, so the last installment is never extra. */
    public static function installmentFor(string $total, int $months): string
    {
        $months = max(1, $months);

        return Money::round(ceil(round((float) $total * 1000 / $months, 6)) / 1000);
    }

    /**
     * Terms of a loan, computed on the server.
     *
     * @return array{profit: string, total: string, installment: string, months: int}
     */
    public static function terms(string $type, mixed $amount, mixed $rate = 0, ?int $months = null, mixed $installment = null): array
    {
        $amount = Money::round($amount);
        $profit = $type === 'financing' ? Money::percent($amount, $rate) : '0';
        $total = Money::add($amount, $profit);
        if ($type === 'financing' || ($months && ! Money::isPositive($installment ?? 0))) {
            $months = max(1, (int) $months);
            $installment = self::installmentFor($total, $months);
        } else {
            $installment = Money::round(Money::min($installment, $total));
            $months = (int) ceil((float) $total / max(0.001, (float) $installment));
        }

        return ['profit' => $profit, 'total' => $total, 'installment' => $installment, 'months' => $months];
    }

    public function give(Employee $e, array $d, int $bankAccountId): EmployeeLoan
    {
        $type = ($d['loan_type'] ?? 'advance') === 'financing' ? 'financing' : 'advance';
        $t = self::terms($type, $d['amount'], $d['profit_rate'] ?? 0, isset($d['installments_count']) ? (int) $d['installments_count'] : null, $d['installment'] ?? null);
        $first = ! empty($d['first_month']) ? Carbon::parse($d['first_month'])->startOfMonth() : now()->addMonthNoOverflow()->startOfMonth();

        return DB::transaction(function () use ($e, $d, $type, $t, $first, $bankAccountId) {
            $loan = EmployeeLoan::create([
                'employee_id' => $e->id, 'date' => now()->toDateString(), 'loan_type' => $type, 'amount' => Money::round($d['amount']),
                'profit_rate' => $type === 'financing' ? (string) ($d['profit_rate'] ?? 0) : 0, 'profit_amount' => $t['profit'], 'total_payable' => $t['total'],
                'installment' => $t['installment'], 'installments_count' => $t['months'], 'first_month' => $first->toDateString(),
                'remaining' => $t['total'], 'profit_collected' => 0, 'status' => 'active', 'notes' => $d['notes'] ?? null,
            ]);
            $je = $this->posting->post([
                'date' => now()->toDateString(), 'description' => ($type === 'financing' ? __('Employee financing') : __('Salary advance')).' - '.$e->name, 'source' => $loan,
                'lines' => [['account' => 'employee_advances', 'debit' => $loan->amount, 'party' => $e],
                    ['account' => BankAccount::query()->findOrFail($bankAccountId)->account_id, 'credit' => $loan->amount]],
            ]);
            $loan->update(['journal_entry_id' => $je->id]);
            AuditLogger::log('loan.given', $loan, ['type' => $type, 'amount' => (string) $loan->amount, 'profit' => $t['profit']]);

            return $loan;
        });
    }

    /** Principal / profit split of a collection (cumulative, so the profit share always adds up exactly). */
    public function split(EmployeeLoan $loan, string $take): array
    {
        $take = Money::min($take, $loan->remaining);
        $collectedAfter = Money::add(Money::sub($loan->total_payable, $loan->remaining), $take);
        if (! Money::isPositive($loan->profit_amount)) {
            return ['amount' => $take, 'principal' => $take, 'profit' => '0'];
        }
        $profitDue = Money::cmp($collectedAfter, $loan->total_payable) >= 0
            ? (string) $loan->profit_amount
            : Money::round(Money::div(Money::mul($loan->profit_amount, $collectedAfter), $loan->total_payable, 6));
        $profit = Money::max('0', Money::min($take, Money::sub($profitDue, $loan->profit_collected)));

        return ['amount' => $take, 'principal' => Money::sub($take, $profit), 'profit' => $profit];
    }

    /** Record a collection on the loan (the journal entry is made by the caller). */
    public function collect(EmployeeLoan $loan, string $take, string $source, ?int $payrollId = null, ?int $journalEntryId = null, ?string $date = null, ?string $notes = null): array
    {
        $s = $this->split($loan, $take);
        if (! Money::isPositive($s['amount'])) {
            return $s;
        }
        $remaining = Money::sub($loan->remaining, $s['amount']);
        $loan->update(['remaining' => $remaining, 'profit_collected' => Money::add($loan->profit_collected, $s['profit']),
            'status' => Money::isPositive($remaining) ? 'active' : 'settled']);
        EmployeeLoanPayment::create(['employee_loan_id' => $loan->id, 'date' => $date ?? now()->toDateString(), 'amount' => $s['amount'],
            'principal' => $s['principal'], 'profit' => $s['profit'], 'source' => $source, 'payroll_id' => $payrollId,
            'journal_entry_id' => $journalEntryId, 'notes' => $notes, 'created_by' => auth()->id()]);

        return $s;
    }

    /** A payroll that is calculated but not posted yet already holds this employee's loan deduction. */
    public function pendingPayrollDeduction(int $employeeId): bool
    {
        return DB::table('payroll_items as i')->join('payrolls as p', 'p.id', '=', 'i.payroll_id')
            ->where('i.employee_id', $employeeId)->where('p.company_id', tenant()->id())->where('p.status', '!=', 'posted')
            ->where('i.loan_deduction', '>', 0)->exists();
    }

    /** Repayment in cash / by transfer, or early settlement of the whole balance. */
    public function repay(EmployeeLoan $loan, mixed $amount, int $bankAccountId, bool $settle = false, ?string $notes = null): EmployeeLoanPayment
    {
        return DB::transaction(function () use ($loan, $amount, $bankAccountId, $settle, $notes) {
            $loan = EmployeeLoan::query()->with('employee')->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            if ($loan->status !== 'active' || ! Money::isPositive($loan->remaining)) {
                throw new BusinessException(__('This loan is already settled.'));
            }
            if ($this->pendingPayrollDeduction($loan->employee_id)) {
                throw new BusinessException(__('A payroll that is not posted yet already deducts this loan. Post or delete that payroll first.'));
            }
            $take = $settle ? (string) $loan->remaining : Money::round($amount);
            if (! Money::isPositive($take) || Money::cmp($take, $loan->remaining) > 0) {
                throw new BusinessException(__('The amount must be between 0 and the outstanding balance :b.', ['b' => money($loan->remaining)]));
            }
            $s = $this->split($loan, $take);
            $je = $this->posting->post([
                'date' => now()->toDateString(), 'description' => __('Loan repayment').' - '.$loan->employee?->name, 'source' => $loan,
                'purpose' => 'repayment-'.now()->format('YmdHisu'),
                'lines' => [['account' => BankAccount::query()->findOrFail($bankAccountId)->account_id, 'debit' => $s['amount']],
                    ['account' => 'employee_advances', 'credit' => $s['principal'], 'party' => $loan->employee],
                    ['account' => 'financing_income', 'credit' => $s['profit']]],
            ]);
            $this->collect($loan, $take, $settle ? 'settlement' : 'manual', null, $je->id, null, $notes);
            AuditLogger::log($settle ? 'loan.settled' : 'loan.repaid', $loan, ['amount' => $take]);

            return EmployeeLoanPayment::query()->where('employee_loan_id', $loan->id)->latest('id')->first();
        });
    }

    /**
     * Installment schedule with what is paid: filled in order from the total collected (handles partial and extra payments).
     *
     * @return array<int, array{n: int, month: Carbon, due: string, paid: string, balance: string, status: string}>
     */
    public function schedule(EmployeeLoan $loan): array
    {
        $first = $loan->first_month ? Carbon::parse($loan->first_month) : $loan->date->copy()->startOfMonth();
        $collected = Money::sub($loan->total_payable, $loan->remaining);
        $rows = [];
        $left = (string) $loan->total_payable;
        $pool = $collected;
        for ($n = 1; Money::isPositive($left) && $n <= 600; $n++) {
            $due = Money::min($loan->installment, $left);
            $paid = Money::min($due, Money::max('0', $pool));
            $pool = Money::sub($pool, $paid);
            $left = Money::sub($left, $due);
            $month = $first->copy()->addMonthsNoOverflow($n - 1);
            $status = Money::cmp($paid, $due) >= 0 ? 'paid' : (Money::isPositive($paid) ? 'partial' : ($month->copy()->endOfMonth()->isPast() ? 'overdue' : 'due'));
            $rows[] = ['n' => $n, 'month' => $month, 'due' => $due, 'paid' => $paid, 'balance' => $left, 'status' => $status];
        }

        return $rows;
    }
}
