<?php

namespace Tests\Feature;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\FundTransfer;
use App\Modules\Accounting\Services\FinanceOperations;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Services\PayrollService;
use App\Modules\Sales\Services\PaymentService;
use App\Support\DocumentNumber;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    public function test_every_money_document_prints_an_official_voucher(): void
    {
        $this->makeTenant();
        $cash = BankAccount::query()->where('type', 'cash')->orderBy('id')->first();
        $bank = BankAccount::query()->where('type', 'bank')->first();
        $customer = $this->makeCustomer(['name' => 'Said Al Amri']);

        $receipt = app(PaymentService::class)->receiveFromCustomer($customer, '1235.500', $cash->id, 'cash');
        $refund = app(PaymentService::class)->refundCustomer($customer, '10', $cash->id);
        $ops = app(FinanceOperations::class);
        $expense = Expense::create(['number' => DocumentNumber::next('expense'), 'date' => now()->toDateString(), 'status' => 'draft',
            'account_id' => Account::query()->where('system_key', 'general_expense')->value('id'), 'bank_account_id' => $cash->id,
            'amount' => 120, 'tax_amount' => 6, 'total' => 126, 'description' => 'A/C maintenance']);
        $ops->postExpense($expense);
        $transfer = FundTransfer::create(['number' => DocumentNumber::next('fund_transfer'), 'date' => now()->toDateString(), 'status' => 'draft',
            'from_bank_account_id' => $cash->id, 'to_bank_account_id' => $bank->id, 'amount' => 500, 'reference' => 'DEP-1']);
        $ops->postTransfer($transfer);
        $employee = Employee::create(['code' => 'E-1', 'name' => 'Khalid', 'hire_date' => now()->subYear()->toDateString(), 'basic_salary' => 400]);
        $loan = app(PayrollService::class)->giveLoan($employee, 300, 50, $cash->id);
        tenant()->forget();

        $this->get('/vouchers/payments/'.$receipt->id)->assertOk()
            ->assertSee('سند قبض')->assertSee('Receipt Voucher')->assertSee($receipt->number)
            ->assertSee('فقط ألف ومائتان وخمسة وثلاثون ريالاً عمانياً وخمسمائة بيسة لا غير')
            ->assertSee('Said Al Amri')->assertSee('1,235')->assertSee('500');
        $this->get('/vouchers/payments/'.$refund->id)->assertOk()->assertSee('سند صرف (استرداد)');
        $this->get('/vouchers/payments/'.$receipt->id.'?paper=a4')->assertOk()->assertSee('ORIGINAL')->assertSee('COPY');
        $this->get('/vouchers/expenses/'.$expense->id)->assertOk()->assertSee('سند صرف')->assertSee('ضريبة القيمة المضافة');
        $this->get('/vouchers/transfers/'.$transfer->id)->assertOk()->assertSee('سند تحويل نقدية')->assertSee('DEP-1');
        $this->get('/vouchers/loans/'.$loan->id)->assertOk()->assertSee('سند صرف سلفة')->assertSee('Khalid');

        // the old print link of a payment now opens the official voucher
        $this->get('/payments/'.$receipt->id.'?print=1')->assertRedirect(route('vouchers.payment', $receipt));

        // another company cannot open these vouchers
        $this->makeTenant('other', 'admin@other.test');
        tenant()->forget();
        $this->get('/vouchers/payments/'.$receipt->id)->assertNotFound();
        $this->get('/vouchers/expenses/'.$expense->id)->assertNotFound();
    }
}
