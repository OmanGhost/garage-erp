<?php

namespace Tests\Feature;

use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\HR\Models\Employee;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Models\CashRegister;
use App\Modules\Sales\Services\PaymentService;
use App\Modules\Sales\Services\PosService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardFeeEmployeePhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    protected function cardTerminal(): BankAccount
    {
        $card = BankAccount::query()->where('type', 'card')->first();
        $this->assertNotNull($card, 'a card terminal account is provisioned');

        return $card;
    }

    public function test_card_commission_is_deducted_with_a_maximum_per_transaction(): void
    {
        $card = $this->cardTerminal();
        $this->put('/m/bank-accounts/'.$card->id, ['name' => $card->name, 'type' => 'card', 'account_id' => $card->account_id, 'branch_id' => $card->branch_id,
            'card_fee_percent' => '1.20', 'card_fee_max' => '10', 'is_active' => 1])->assertSessionHasNoErrors();
        $card->refresh();
        $this->assertSame(['1.200', '10.000'], [(string) $card->card_fee_percent, (string) $card->card_fee_max]);
        $this->assertSame('6.000', $card->cardFee('500'));
        $this->assertSame('10.000', $card->cardFee('1000'));            // 12.000 capped at 10.000

        $c = $this->makeCustomer();
        $svc = app(PaymentService::class);
        $p1 = $svc->receiveFromCustomer($c, 500, $card->id, 'card');
        $p2 = $svc->receiveFromCustomer($c, 1000, $card->id, 'card');
        $this->assertSame(['6.000', '494.000'], [(string) $p1->fee_amount, (string) $p1->net_amount]);
        $this->assertSame(['10.000', '990.000'], [(string) $p2->fee_amount, (string) $p2->net_amount]);
        $this->assertMoney('1484', Ledger::accountBalance($card->account_id));      // exactly what the bank deposits
        $this->assertMoney('16', $this->balance('bank_charges'));
        $this->assertMoney('-1500', $this->balance('ar'));                          // the customer is credited in full

        // cash has no commission; cancelling a card receipt reverses its commission too
        $cash = $svc->receiveFromCustomer($c, 100, $this->cashAccountId(), 'cash');
        $this->assertSame('0.000', (string) $cash->fee_amount);
        $svc->cancel($p2, 'Wrong card');
        $this->assertMoney('6', $this->balance('bank_charges'));
        $this->assertLedgerBalanced();

        // no maximum: the plain percentage
        $card->update(['card_fee_max' => null]);
        $this->assertSame('12.000', $card->fresh()->cardFee('1000'));
    }

    public function test_pos_session_summary_shows_commission_and_net_to_the_bank(): void
    {
        $this->cardTerminal()->update(['card_fee_percent' => 1.2, 'card_fee_max' => 10]);
        $part = $this->makePart(['sale_price' => 100]);
        $register = CashRegister::query()->firstOrFail();
        app(StockService::class)->receive($part, $register->warehouse_id, 5, 40, ['type' => 'opening']);
        $pos = app(PosService::class);
        $session = $pos->openSession($register, 0);
        $pos->checkout($session, ['customer_id' => (int) setting('pos.walk_in_customer_id'), 'items' => [['part_id' => $part->id, 'qty' => 1]]],
            [['method' => 'card', 'amount' => 105]]);
        $sm = $pos->summary($session->fresh());
        $this->assertSame('1.260', $sm['fees']);
        $this->get('/pos/sessions/'.$session->id.'/close-summary')->assertOk()->assertSee('103.740');
        $this->assertLedgerBalanced();
    }

    public function test_employee_photo_upload(): void
    {
        Storage::fake('public');
        $this->post('/m/employees', ['name' => 'سالم', 'basic_salary' => 300, 'hire_date' => '2026-01-01', 'status' => 'active',
            'photo_path' => UploadedFile::fake()->image('salim.jpg', 300, 300)])->assertSessionHasNoErrors();
        $e = Employee::query()->where('name', 'سالم')->firstOrFail();
        $this->assertNotNull($e->photo_path);
        Storage::disk('public')->assertExists($e->photo_path);
        $this->get('/m/employees')->assertOk()->assertSee($e->photoUrl(), false);
        $this->post('/m/employees', ['name' => 'Bad', 'basic_salary' => 300, 'status' => 'active',
            'photo_path' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php')])->assertSessionHasErrors('photo_path');
    }
}
