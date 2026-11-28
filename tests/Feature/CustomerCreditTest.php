<?php

namespace Tests\Feature;

use App\Livewire\PosTerminal;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Models\CashRegister;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Services\CreditNoteService;
use App\Modules\Sales\Services\CustomerCreditService;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Sales\Services\PaymentService;
use App\Modules\Sales\Services\PosService;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerCreditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    protected function invoice($customer, string $price): Invoice
    {
        $svc = app(InvoiceService::class);

        return $svc->post($svc->createDraft(['customer_id' => $customer->id, 'payment_terms' => 'credit'],
            [['item_type' => 'service', 'description' => 'Service', 'qty' => 1, 'unit_price' => $price, 'tax_rate' => 0]]));
    }

    public function test_advance_payment_pays_a_later_invoice_without_moving_cash(): void
    {
        $c = $this->makeCustomer();
        $credit = app(CustomerCreditService::class);

        // advance (no invoice selected)
        $this->get('/payments/receive?customer_id='.$c->id.'&advance=1')->assertOk()->assertSee(__('Advance payment: leave every invoice empty — the amount stays as the customer credit balance and can pay future invoices.'));
        $this->post('/payments/receive', ['customer_id' => $c->id, 'amount' => 100, 'bank_account_id' => $this->cashAccountId(), 'method' => 'cash', 'date' => now()->toDateString()])->assertRedirect();
        $this->assertSame('100.000', $credit->available($c));
        $this->get('/customers/'.$c->id)->assertSee(__('Credit balance'));

        $inv = $this->invoice($c, '70');
        $this->assertSame('100.000', $credit->available($c));                  // an unpaid invoice does not use the credit by itself
        $this->get('/invoices/'.$inv->id)->assertOk()->assertSee(__('Pay from balance'));
        $cashBefore = $this->balance('cash');
        $this->post('/invoices/'.$inv->id.'/pay-from-balance', [])->assertSessionHas('success');
        $inv->refresh();
        $this->assertSame('paid', $inv->status);
        $this->assertSame('30.000', $credit->available($c));
        $this->assertSame($cashBefore, $this->balance('cash'));                // no cash moved
        $this->assertMoney('-30', $c->balance());
        $this->assertLedgerBalanced();

        // more than the balance / than the invoice is refused
        $inv2 = $this->invoice($c, '50');
        $this->post('/invoices/'.$inv2->id.'/pay-from-balance', ['amount' => 40])->assertSessionHas('error');
        $this->post('/invoices/'.$inv2->id.'/pay-from-balance', ['amount' => 30])->assertSessionHas('success');
        $this->assertSame(['partially_paid', '20.000'], [$inv2->fresh()->status, $inv2->fresh()->balanceDue()]);
        $this->assertSame('0.000', $credit->available($c));

        // cancelling the balance receipt gives the credit back
        app(PaymentService::class)->cancel(Payment::query()->where('method', 'balance')->latest('id')->first(), 'Mistake');
        $this->assertSame('30.000', $credit->available($c));
        $this->assertSame('posted', $inv2->fresh()->status);
    }

    public function test_credit_note_not_refunded_becomes_usable_credit(): void
    {
        $c = $this->makeCustomer();
        $part = $this->makePart(['sale_price' => 40]);
        app(StockService::class)->receive($part, $this->mainWarehouse()->id, 5, 10, ['type' => 'opening']);
        $svc = app(InvoiceService::class);
        $inv = $svc->post($svc->createDraft(['customer_id' => $c->id, 'warehouse_id' => $this->mainWarehouse()->id, 'payment_terms' => 'credit'],
            [['item_type' => 'part', 'part_id' => $part->id, 'qty' => 2, 'unit_price' => 40, 'tax_rate' => 0]]));
        app(PaymentService::class)->receiveFromCustomer($c, 80, $this->cashAccountId(), 'cash');
        $cns = app(CreditNoteService::class);
        $cns->post($cns->create($inv->fresh(), [$inv->items()->first()->id => 1], true, 'Returned'));   // no refund → credit
        $this->assertSame('40.000', app(CustomerCreditService::class)->available($c));
    }

    public function test_pos_customer_balance_payment(): void
    {
        $c = $this->makeCustomer(['name' => 'Credit Customer']);
        app(PaymentService::class)->receiveFromCustomer($c, 5, $this->cashAccountId(), 'cash');           // 5.000 credit
        $part = $this->makePart(['barcode' => '6281000000077', 'sale_price' => 10]);
        $register = CashRegister::query()->firstOrFail();
        app(StockService::class)->receive($part, $register->warehouse_id, 5, 4, ['type' => 'opening']);
        $session = app(PosService::class)->openSession($register, 0);

        // 10 + 5% = 10.500: 5.000 from the balance, 5.500 cash
        Livewire::test(PosTerminal::class, ['session' => $session])->call('pickCustomer', $c->id)->call('scanned', '6281000000077')
            ->assertSee(__('Use customer balance (:a)', ['a' => money('5', false)]))
            ->call('useBalance')->assertSet('payments', [['method' => 'balance', 'amount' => '5.000'], ['method' => 'cash', 'amount' => '5.500']])
            ->call('checkout')->assertSet('cart', []);
        $inv = Invoice::query()->where('type', 'pos')->latest('id')->firstOrFail();
        $this->assertSame('paid', $inv->status);
        $this->assertSame('0.000', app(CustomerCreditService::class)->available($c));
        $this->assertMoney('5.5', app(PosService::class)->expectedCash($session->fresh()));        // only the cash part is in the drawer
        $this->get(route('invoices.print', [$inv, '80mm']))->assertSee('From credit balance');
        $this->assertLedgerBalanced();

        // the walk-in customer never has a usable balance
        $this->assertSame('0', app(CustomerCreditService::class)->available(\App\Modules\CRM\Models\Customer::query()->find(setting('pos.walk_in_customer_id'))));
    }
}
