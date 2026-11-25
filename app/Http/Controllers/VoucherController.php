<?php

namespace App\Http\Controllers;

use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\FundTransfer;
use App\Modules\HR\Models\EmployeeLoan;
use App\Modules\HR\Models\Payroll;
use App\Modules\Sales\Models\Payment;
use App\Support\Audit\AuditLogger;
use App\Support\Vouchers\Voucher;

/**
 * Official printable receipt / payment / transfer vouchers for every money document.
 * Route model binding is tenant-scoped, so a voucher of another company is never reachable.
 */
class VoucherController extends Controller
{
    protected function render(Voucher $v, $model)
    {
        AuditLogger::log('voucher.printed', $model, ['number' => $v->number]);

        return response()->view('vouchers.print', ['v' => $v]);
    }

    public function payment(Payment $payment)
    {
        abort_unless(auth()->user()->can('payments.print') || auth()->user()->can('payments.view'), 403);

        return $this->render(Voucher::fromPayment($payment->load('allocations.allocatable', 'bankAccount', 'party', 'creator')), $payment);
    }

    public function expense(Expense $expense)
    {
        abort_unless(auth()->user()->can('expenses.view'), 403);

        return $this->render(Voucher::fromExpense($expense->load('account', 'bankAccount', 'supplier', 'costCenter')), $expense);
    }

    public function transfer(FundTransfer $transfer)
    {
        abort_unless(auth()->user()->can('fund_transfers.view'), 403);

        return $this->render(Voucher::fromTransfer($transfer->load('fromAccount', 'toAccount')), $transfer);
    }

    public function loan(EmployeeLoan $loan)
    {
        abort_unless(auth()->user()->can('loans.view'), 403);

        return $this->render(Voucher::fromLoan($loan->load('employee')), $loan);
    }

    public function payroll(Payroll $payroll)
    {
        abort_unless(auth()->user()->can('payroll.view'), 403);
        abort_unless($payroll->status === 'paid', 404);

        return $this->render(Voucher::fromPayroll($payroll), $payroll);
    }
}
