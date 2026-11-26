<?php

namespace Tests\Feature;

use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Workshop\Models\JobCard;
use Tests\TestCase;

class GatePassDeliveryNoteTest extends TestCase
{
    protected \App\Modules\Core\Models\Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = $this->makeTenant();
        $this->app->setLocale('ar');
    }

    protected function receive(): JobCard
    {
        $this->post('/reception', ['customer_name' => 'Khalfan Al Hinai', 'customer_phone' => '92223344', 'plate_number' => '4567', 'plate_code' => 'BD',
            'odometer' => 50000, 'fuel_level' => 50, 'reported_issues' => 'Service', '_idem' => uniqid()])->assertRedirect();

        return tenant()->run($this->company->id, fn () => JobCard::query()->with('vehicle', 'customer')->latest('id')->firstOrFail());
    }

    public function test_gate_pass_shows_release_and_payment_status(): void
    {
        $jc = $this->receive();

        // not delivered yet: the pass says the vehicle may not leave
        $this->get('/job-cards/'.$jc->id.'/gate-pass')->assertOk()
            ->assertSee('تصريح خروج مركبة')->assertSee(preg_replace('/^[A-Z]+-/', 'GP-', $jc->number))->assertSee('4567')->assertSee('Khalfan Al Hinai')
            ->assertSee('غير مسموح بالخروج');
        $this->get('/job-cards/'.$jc->id)->assertOk()->assertSee(route('job-cards.gate-pass', $jc), false);

        // unpaid workshop invoice, then delivery
        $wh = $this->mainWarehouse()->id;
        $part = $this->makePart(['sale_price' => 10]);
        app(StockService::class)->receive($part, $wh, 5, 4, ['type' => 'opening']);
        $svc = app(InvoiceService::class);
        $inv = $svc->post($svc->createDraft(['customer_id' => $jc->customer_id, 'warehouse_id' => $wh, 'job_card_id' => $jc->id, 'payment_terms' => 'credit'], [
            ['item_type' => 'part', 'part_id' => $part->id, 'qty' => 2, 'unit_price' => 10, 'tax_rate' => 5],
        ]));
        tenant()->run($this->company->id, fn () => JobCard::query()->whereKey($jc->id)->update(['status' => 'delivered', 'delivered_at' => now()]));

        $this->get('/job-cards/'.$jc->id.'/gate-pass')->assertOk()
            ->assertSee('مسموح بالخروج')->assertSee($inv->number)->assertSee('يوجد مبلغ مستحق')->assertSee('21.000');
        $this->get('/job-cards/'.$jc->id)->assertOk()->assertSee('طباعة تصريح الخروج');
        $this->get('/job-cards/'.$jc->id.'/gate-pass/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/invoices/'.$inv->id)->assertOk()->assertSee(route('job-cards.gate-pass', $jc), false);
    }

    public function test_delivery_note_lists_goods_without_prices(): void
    {
        $wh = $this->mainWarehouse()->id;
        $part = $this->makePart(['sale_price' => 12.5, 'sku' => 'OF-90915']);
        app(StockService::class)->receive($part, $wh, 10, 4, ['type' => 'opening']);
        $svc = app(InvoiceService::class);
        $inv = $svc->post($svc->createDraft(['customer_id' => $this->makeCustomer(['name' => 'Salim Parts Buyer'])->id, 'warehouse_id' => $wh], [
            ['item_type' => 'part', 'part_id' => $part->id, 'qty' => 3, 'unit_price' => 12.5, 'tax_rate' => 5],
            ['item_type' => 'service', 'description' => 'Fitting labour', 'qty' => 1, 'unit_price' => 5, 'tax_rate' => 5],
        ]));

        $page = $this->get('/invoices/'.$inv->id.'/delivery-note')->assertOk()
            ->assertSee('إشعار تسليم')->assertSee(preg_replace('/^[A-Z]+-/', 'DN-', $inv->number))->assertSee('OF-90915')->assertSee('Salim Parts Buyer')
            ->assertSee('لا يتضمن هذا الإشعار الخدمات')->assertDontSee('Fitting labour');
        $this->assertStringNotContainsString('12.500', $page->getContent()); // no prices on a delivery note
        $this->get('/invoices/'.$inv->id.'/delivery-note/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/invoices/'.$inv->id)->assertOk()->assertSee(route('invoices.delivery-note', $inv), false);
        $this->get('/m/invoices')->assertOk()->assertSee(route('invoices.delivery-note', $inv), false);
    }
}
