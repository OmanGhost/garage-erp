<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeLoan;
use App\Modules\HR\Services\AttendanceService;
use App\Modules\HR\Services\LoanService;
use App\Modules\HR\Services\PayrollService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoanAttendanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function employee(array $attrs = []): Employee
    {
        static $n = 0;
        $n++;

        return Employee::create($attrs + ['code' => 'E-'.$n, 'name' => 'Employee '.$n, 'hire_date' => '2025-01-01', 'basic_salary' => 1000, 'status' => 'active']);
    }

    protected function runPayroll(string $month): void
    {
        $svc = app(PayrollService::class);
        $p = $svc->generate(Carbon::parse($month.'-01'));
        $svc->approve($p);
        $svc->post($p->fresh());
    }

    public function test_internal_financing_is_repaid_through_payroll_with_profit_booked_as_income(): void
    {
        $e = $this->employee();
        $loan = app(LoanService::class)->give($e, ['loan_type' => 'financing', 'amount' => 1000, 'profit_rate' => 10, 'installments_count' => 10, 'first_month' => '2026-01-01'],
            $this->cashAccountId());
        $this->assertSame('100.000', (string) $loan->profit_amount);
        $this->assertSame('1100.000', (string) $loan->total_payable);
        $this->assertSame('110.000', (string) $loan->installment);
        $this->assertMoney('1000', $this->balance('employee_advances'));          // only the principal is an advance

        $this->runPayroll('2026-01');
        $loan->refresh();
        $this->assertSame('990.000', (string) $loan->remaining);
        $first = $loan->payments()->first();
        $this->assertSame(['110.000', '100.000', '10.000', 'payroll'], [(string) $first->amount, (string) $first->principal, (string) $first->profit, $first->source]);
        $this->assertMoney('900', $this->balance('employee_advances'));
        $this->assertMoney('-10', $this->balance('financing_income'));

        foreach (range(2, 10) as $m) {
            $this->runPayroll('2026-'.str_pad((string) $m, 2, '0', STR_PAD_LEFT));
        }
        $loan->refresh();
        $this->assertSame('settled', $loan->status);
        $this->assertSame('0.000', (string) $loan->remaining);
        $this->assertSame('100.000', (string) $loan->profit_collected);
        $this->assertMoney('0', $this->balance('employee_advances'));
        $this->assertMoney('-100', $this->balance('financing_income'));
        $this->assertLedgerBalanced();
        $this->assertSame(10, $loan->payments()->count());
        $this->assertSame(['paid'], array_values(array_unique(array_column(app(LoanService::class)->schedule($loan), 'status'))));
    }

    public function test_profit_is_collected_exactly_whatever_the_rounding(): void
    {
        $svc = app(LoanService::class);
        $loan = $svc->give($this->employee(), ['loan_type' => 'financing', 'amount' => 1000, 'profit_rate' => 7, 'installments_count' => 3], $this->cashAccountId());
        $this->assertSame('356.667', (string) $loan->installment);
        $splits = [];
        foreach (range(1, 3) as $i) {
            $loan->refresh();
            $splits[] = $svc->collect($loan, \App\Support\Money::min($loan->installment, $loan->remaining), 'manual');
        }
        $loan->refresh();
        $this->assertSame('356.666', $splits[2]['amount']);                     // the last installment absorbs the rounding
        $this->assertSame('70.000', (string) $loan->profit_collected);
        $this->assertSame('settled', $loan->status);
        $this->assertSame(3, count($svc->schedule($loan)));
    }

    public function test_manual_repayment_early_settlement_and_payroll_guard(): void
    {
        $e = $this->employee();
        $svc = app(LoanService::class);
        $loan = $svc->give($e, ['loan_type' => 'advance', 'amount' => 300, 'installment' => 100, 'first_month' => '2026-03-01'], $this->cashAccountId());
        $this->assertSame(3, $loan->installments_count);

        // February is before the first installment month: nothing deducted
        $this->runPayroll('2026-02');
        $this->assertSame('300.000', (string) $loan->fresh()->remaining);

        // a March payroll that is calculated but not posted already holds the deduction
        $p = app(PayrollService::class)->generate(Carbon::parse('2026-03-01'));
        $this->post('/hr/loans/'.$loan->id.'/repay', ['amount' => 50, 'bank_account_id' => $this->cashAccountId()])->assertSessionHas('error');
        app(PayrollService::class)->approve($p);
        app(PayrollService::class)->post($p->fresh());
        $this->assertSame('200.000', (string) $loan->fresh()->remaining);

        $this->post('/hr/loans/'.$loan->id.'/repay', ['amount' => 50, 'bank_account_id' => $this->cashAccountId(), 'notes' => 'Cash'])->assertSessionHas('success');
        $this->assertSame('150.000', (string) $loan->fresh()->remaining);
        $this->post('/hr/loans/'.$loan->id.'/repay', ['settle' => 1, 'bank_account_id' => $this->cashAccountId()])->assertSessionHas('success');
        $loan->refresh();
        $this->assertSame('settled', $loan->status);
        $this->assertSame(['payroll', 'manual', 'settlement'], $loan->payments()->pluck('source')->all());
        $this->assertMoney('0', $this->balance('employee_advances'));
        $this->assertLedgerBalanced();

        $this->get('/hr/loans/'.$loan->id)->assertOk()->assertSee(__('Installment schedule'))->assertSee(__('Early settlement'));
    }

    public function test_loan_form_and_voucher(): void
    {
        $e = $this->employee(['name' => 'Saeed']);
        $this->get('/hr/loans/create')->assertOk()->assertSee(__('Internal financing'));
        $this->post('/hr/loans', ['employee_id' => $e->id, 'loan_type' => 'financing', 'amount' => 600, 'profit_rate' => 5, 'installments_count' => 6,
            'installment' => 1, 'first_month' => now()->addMonthNoOverflow()->format('Y-m'), 'bank_account_id' => $this->cashAccountId()])->assertRedirect();
        $loan = EmployeeLoan::query()->firstOrFail();
        $this->assertSame('630.000', (string) $loan->total_payable);           // server-side terms, posted installment ignored
        $this->assertSame('105.000', (string) $loan->installment);
        $this->get('/hr/loans/'.$loan->id)->assertOk()->assertSee('630.000')->assertSee(__('Upcoming'));
        $this->get('/vouchers/loans/'.$loan->id)->assertOk()->assertSee('تمويل داخلي');
        $this->get('/m/employee-loans')->assertOk()->assertSee('Saeed');
    }

    public function test_one_click_attendance(): void
    {
        $user = auth()->user();
        $e = $this->employee(['user_id' => $user->id]);

        Carbon::setTestNow('2026-11-25 08:30:00');                               // Wednesday, opens 08:00 (+10 min grace)
        $this->get('/dashboard')->assertSee(__('Clock in'));
        $this->post('/attendance/punch')->assertSessionHas('success');
        $a = Attendance::query()->where('employee_id', $e->id)->firstOrFail();
        $this->assertSame(['late', 30, 'self'], [$a->status, (int) $a->late_minutes, $a->source]);
        $this->assertNotNull($a->check_in_ip);

        Carbon::setTestNow('2026-11-25 08:31:00');
        $this->post('/attendance/punch')->assertSessionHas('error');             // double click does not end the day
        Carbon::setTestNow('2026-11-25 17:00:00');
        $this->get('/dashboard')->assertSee(__('Clock out'));
        $this->post('/attendance/punch')->assertSessionHas('success');
        $a->refresh();
        $this->assertSame(510, (int) $a->worked_minutes);
        $this->assertSame(30, (int) $a->overtime_minutes);
        Carbon::setTestNow('2026-11-25 17:30:00');
        $this->post('/attendance/punch')->assertSessionHas('error');             // already checked out today

        // night shift: closes the open attendance of the previous day
        Carbon::setTestNow('2026-11-26 22:00:00');
        $this->post('/attendance/punch');
        Carbon::setTestNow('2026-11-27 06:00:00');
        $this->post('/attendance/punch');
        $night = Attendance::query()->where('employee_id', $e->id)->whereDate('date', '2026-11-26')->firstOrFail();
        $this->assertSame(480, (int) $night->worked_minutes);
        $this->assertSame(0, Attendance::query()->where('employee_id', $e->id)->whereDate('date', '2026-11-27')->count());

        // a user who is not an employee has no button and cannot punch
        $this->actAsRole('Cashier');
        $this->get('/dashboard')->assertDontSee(__('Clock in'));
        $this->post('/attendance/punch')->assertSessionHas('error');            // refused with a message
        $this->assertSame(1, Attendance::query()->whereDate('date', '2026-11-27')->count() + 1);
        $this->assertNull(AttendanceService::employeeFor(User::query()->where('is_super_admin', true)->first()));
    }
}
