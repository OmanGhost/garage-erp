<?php

namespace Tests\Feature;

use App\Livewire\KanbanBoard;
use App\Livewire\PosTerminal;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Models\CashRegister;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Services\PosService;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Services\JobCardService;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    public function test_pos_terminal_scan_hold_resume_and_checkout(): void
    {
        $part = $this->makePart(['barcode' => '6281000000017', 'sale_price' => 5]);
        $register = CashRegister::query()->firstOrFail();
        app(StockService::class)->receive($part, $register->warehouse_id, 10, 2, ['type' => 'opening']);
        $session = app(PosService::class)->openSession($register, 10);

        $c = Livewire::test(PosTerminal::class, ['session' => $session])
            ->call('scanned', '6281000000017')
            ->call('scanned', '6281000000017')
            ->assertSet('cart.0.qty', '2');

        // 2 x 5 + 5% = 10.500
        $this->assertSame('10.500', $c->instance()->totals()['total']);

        $c->call('hold')->assertSet('cart', []);
        $held = Invoice::query()->where('status', 'held')->firstOrFail();

        $c->call('resume', $held->id)->assertCount('cart', 1)
            ->call('payExact')
            ->call('checkout')
            ->assertSet('cart', []);

        $this->assertSame('paid', $held->fresh()->status);
        $this->assertSame(0, Invoice::query()->where('status', 'held')->count());
        $this->assertLedgerBalanced();
    }

    public function test_pos_cash_popup_records_cash_received_and_change_on_the_receipt(): void
    {
        $part = $this->makePart(['barcode' => '6281000000099', 'sale_price' => 7]);
        $register = CashRegister::query()->firstOrFail();
        app(StockService::class)->receive($part, $register->warehouse_id, 10, 2, ['type' => 'opening']);
        $session = app(PosService::class)->openSession($register, 10);

        // 7 + 5% = 7.350; the customer hands over 10 rials
        $c = Livewire::test(PosTerminal::class, ['session' => $session])->call('scanned', '6281000000099')
            ->assertSee("('pos-cash-open'", false)
            ->call('checkoutCash', '10')->assertSet('cart', [])
            ->assertDispatched('pos-sale-done', fn ($name, $p) => $p['change'] === '2.650' && $p['tendered'] === '10.000');
        $inv = Invoice::query()->where('type', 'pos')->latest('id')->firstOrFail();
        $this->assertSame(['paid', '10.000', '2.650'], [$inv->status, (string) $inv->cash_tendered, (string) $inv->change_given]);
        $this->assertSame('7.350', (string) $inv->paid_amount);                   // only the bill is received; the change goes back
        $this->get(route('invoices.print', [$inv, '80mm']))->assertOk()->assertSee('10.000')->assertSee('2.650')->assertSee('CHANGE');
        $this->assertMoney(17.350, app(PosService::class)->expectedCash($session->fresh()));
        $this->assertLedgerBalanced();

        // split payments keep the normal button; card-only sales have no cash lines on the receipt
        $c->call('scanned', '6281000000099')->call('addPayment')->assertDontSee("('pos-cash-open'", false);
        $c->set('payments', [['method' => 'card', 'amount' => '7.350']])->call('checkout');
        $card = Invoice::query()->where('type', 'pos')->latest('id')->firstOrFail();
        $this->assertNull($card->cash_tendered);
        $this->get(route('invoices.print', [$card, '80mm']))->assertDontSee('CHANGE');
    }

    public function test_pos_part_inquiry_shows_stock_alternatives_and_history(): void
    {
        $register = CashRegister::query()->firstOrFail();
        $other = \App\Modules\Inventory\Models\Warehouse::create(['code' => 'WH2', 'name' => 'Sohar store', 'branch_id' => $register->branch_id, 'is_active' => true]);
        $part = $this->makePart(['sku' => 'BRK-100', 'oem_number' => '04465-0K240', 'name_ar' => 'فحمات فرامل', 'sale_price' => 20, 'wholesale_price' => 16, 'min_stock' => 5]);
        $alt = $this->makePart(['sku' => 'BRK-ALT', 'oem_number' => '04465-0K240', 'name_ar' => 'فحمات بديلة', 'part_type' => 'aftermarket', 'sale_price' => 12]);
        $used = $this->makePart(['sku' => 'USED-1', 'name_ar' => 'فحمات مستعملة', 'part_type' => 'used', 'sale_price' => 5]);
        app(StockService::class)->receive($part, $other->id, 3, 10, ['type' => 'opening']);           // only in the other warehouse
        app(StockService::class)->receive($alt, $register->warehouse_id, 4, 6, ['type' => 'opening']);
        $customer = $this->makeCustomer(['name' => 'Repeat Buyer']);
        $svc = app(\App\Modules\Sales\Services\InvoiceService::class);
        $svc->post($svc->createDraft(['customer_id' => $customer->id, 'warehouse_id' => $other->id, 'payment_terms' => 'credit'],
            [['item_type' => 'part', 'part_id' => $part->id, 'qty' => 1, 'unit_price' => 18, 'tax_rate' => 5]]));
        $session = app(PosService::class)->openSession($register, 0);

        $c = Livewire::test(PosTerminal::class, ['session' => $session])
            ->set('search', 'فحمات')->assertSee('BRK-100')->assertSee('BRK-ALT')->assertSee('USED-1')
            ->set('typeFilter', 'used')->assertSee('USED-1')->assertDontSee('BRK-ALT')
            ->set('typeFilter', 'new')->set('stockOnly', true)->assertSee('BRK-ALT')->assertDontSee('BRK-100')
            ->call('clearFilters')->call('pickCustomer', $customer->id)
            ->set('search', '04465-0K240')->call('inquireSearch')->assertSet('inquiryId', $part->id)
            ->assertSee('Sohar store')->assertSee('BRK-ALT')->assertSee(__('Not available in this branch — available in another warehouse (see below).'))
            ->assertSee('18.000');                                                                     // this customer's last price

        $q = app(\App\Modules\Inventory\Services\PartInquiry::class)->for($part, $register->warehouse_id, $customer->id, 'retail', true);
        $this->assertNull($q['here']);                                   // never stocked in this branch
        $this->assertSame(2.0, (float) $q['total_available']);
        $this->assertTrue($q['low']);
        $this->assertSame('1.000', \App\Support\Money::round($q['sales']['30']));
        $this->assertSame($alt->id, $q['alternatives'][0]['id']);
        $this->assertNotNull($q['cost']);
        $this->assertNull(app(\App\Modules\Inventory\Services\PartInquiry::class)->for($part)['cost']);

        // add an alternative from the panel; cashiers do not see costs
        $c->call('addFromInquiry', $alt->id)->assertCount('cart', 1)->assertSet('inquiryId', $part->id)
            ->call('closeInquiry')->assertSet('inquiryId', null);
        $this->actAsRole('Cashier');
        Livewire::test(PosTerminal::class, ['session' => $session])->call('inquire', $part->id)->assertDontSee(__('Margin'));
        Livewire::test(PosTerminal::class, ['session' => $session])->set('search', 'zz')->call('inquireSearch')->assertSet('inquiryId', null);
    }

    public function test_kanban_move_respects_the_workflow(): void
    {
        $customer = $this->makeCustomer();
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'plate_number' => '7788']);
        $jc = app(JobCardService::class)->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'customer_complaint' => 'Check']);

        $this->actAsRole('Technician');
        Livewire::test(KanbanBoard::class)
            ->call('move', $jc->id, JobCard::UNDER_INSPECTION)
            ->assertDispatched('erp-toast');
        $this->assertSame(JobCard::UNDER_INSPECTION, $jc->fresh()->status);

        // jumping to delivered is refused and reported, not thrown
        Livewire::test(KanbanBoard::class)
            ->call('move', $jc->id, JobCard::DELIVERED)
            ->assertDispatched('erp-toast', type: 'danger');
        $this->assertSame(JobCard::UNDER_INSPECTION, $jc->fresh()->status);
    }
}
