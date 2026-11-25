<?php

namespace Tests\Feature;

use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\FundTransfer;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Sales\Services\PaymentService;
use Tests\TestCase;

class FundTransferTest extends TestCase
{
    protected BankAccount $cash;

    protected BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
        $this->cash = BankAccount::query()->where('type', 'cash')->orderBy('id')->first();
        $this->bank = BankAccount::query()->where('type', 'bank')->first();
        // put 1,000.000 in the main cash box
        app(PaymentService::class)->receiveFromCustomer($this->makeCustomer(), 1000, $this->cash->id, 'cash');
        tenant()->forget();
    }

    public function test_screen_shows_every_account_with_its_current_balance(): void
    {
        $this->get('/m/fund-transfers/create')->assertRedirect(route('accounting.transfers.create'));
        $res = $this->get('/accounting/transfers/create')->assertOk()->assertSee($this->cash->name)->assertSee($this->bank->name);
        // balances are embedded for instant display when an account is selected
        $this->assertStringContainsString('1000.000', $res->getContent());
    }

    public function test_save_and_post_moves_the_money_and_offers_the_voucher(): void
    {
        $this->post('/accounting/transfers', [
            'date' => now()->toDateString(), 'from_bank_account_id' => $this->cash->id, 'to_bank_account_id' => $this->bank->id,
            'amount' => '400', 'reference' => 'DEP-9', 'post_now' => 1, '_idem' => 'x1',
        ])->assertRedirect(route('crud.index', 'fund-transfers'))->assertSessionHas('print_voucher');

        tenant()->set($this->company->id);
        $t = FundTransfer::query()->firstOrFail();
        $this->assertSame('posted', $t->status);
        $this->assertSame('600.000', Ledger::accountBalance($this->cash->account_id));
        $this->assertSame('400.000', Ledger::accountBalance($this->bank->account_id));
    }

    public function test_transfer_above_the_available_balance_is_refused(): void
    {
        $this->post('/accounting/transfers', [
            'date' => now()->toDateString(), 'from_bank_account_id' => $this->cash->id, 'to_bank_account_id' => $this->bank->id,
            'amount' => '1500', 'post_now' => 1, '_idem' => 'x2',
        ])->assertSessionHas('error');

        tenant()->set($this->company->id);
        $this->assertSame(0, FundTransfer::query()->count()); // nothing saved: the whole operation was rolled back
        $this->assertSame('1000.000', Ledger::accountBalance($this->cash->account_id));
    }

    public function test_same_account_is_rejected_and_drafts_can_be_edited(): void
    {
        $this->post('/accounting/transfers', [
            'date' => now()->toDateString(), 'from_bank_account_id' => $this->cash->id, 'to_bank_account_id' => $this->cash->id, 'amount' => '10',
        ])->assertSessionHasErrors('to_bank_account_id');

        $this->post('/accounting/transfers', [
            'date' => now()->toDateString(), 'from_bank_account_id' => $this->cash->id, 'to_bank_account_id' => $this->bank->id, 'amount' => '10', '_idem' => 'x3',
        ])->assertSessionHasNoErrors();
        tenant()->set($this->company->id);
        $t = FundTransfer::query()->firstOrFail();
        tenant()->forget();
        $this->assertSame('draft', $t->status);
        $this->get('/m/fund-transfers/'.$t->id.'/edit')->assertRedirect(route('accounting.transfers.edit', $t));
        $this->get('/accounting/transfers/'.$t->id.'/edit')->assertOk()->assertSee($t->number);
        $this->put('/accounting/transfers/'.$t->id, [
            'date' => now()->toDateString(), 'from_bank_account_id' => $this->cash->id, 'to_bank_account_id' => $this->bank->id, 'amount' => '25', '_idem' => 'x4',
        ])->assertSessionHasNoErrors();
        $this->assertSame('25.000', (string) $t->fresh()->amount);
    }
}
