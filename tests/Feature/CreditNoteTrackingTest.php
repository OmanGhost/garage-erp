<?php

namespace Tests\Feature;

use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Services\CreditNoteService;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Sales\Services\PaymentService;
use Tests\TestCase;

/** Credit notes can be tracked (status, settlement, refund voucher) and printed. */
class CreditNoteTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
        $this->app->setLocale('ar');
    }

    protected function paidInvoice()
    {
        $wh = $this->mainWarehouse()->id;
        $part = $this->makePart(['sale_price' => 10]);
        app(StockService::class)->receive($part, $wh, 10, 4, ['type' => 'opening']);
        $svc = app(InvoiceService::class);
        $inv = $svc->post($svc->createDraft(['customer_id' => $this->makeCustomer(['name' => 'Hamad Al Rawahi'])->id, 'warehouse_id' => $wh], [
            ['item_type' => 'part', 'part_id' => $part->id, 'qty' => 2, 'unit_price' => 10, 'tax_rate' => 5],
        ]));
        app(PaymentService::class)->receiveFromCustomer($inv->customer, $inv->total, $this->cashBankId(), 'cash', [$inv->id => $inv->total]);

        return $inv->fresh('items');
    }

    protected function cashBankId(): int
    {
        return (int) \App\Modules\Accounting\Models\BankAccount::query()->value('id');
    }

    public function test_refunded_credit_note_is_tracked_and_printable(): void
    {
        $inv = $this->paidInvoice();
        $cns = app(CreditNoteService::class);
        $draft = $cns->create($inv, [$inv->items->first()->id => 1], true, 'Wrong part ordered');

        // draft: page explains it has no effect yet, list shows it awaiting posting, print is marked as draft
        $this->get('/credit-notes/'.$draft->id)->assertOk()->assertSee('هذا الإشعار مسودة')->assertSee('بانتظار الاعتماد');
        $this->get('/m/credit-notes')->assertOk()->assertSee('بانتظار الترحيل')->assertSee('Wrong part ordered')->assertSee(route('credit-notes.print', $draft), false);
        $this->get('/credit-notes/'.$draft->id.'/print')->assertOk()->assertSee('مسودة إشعار دائن')->assertSee('DRAFT');

        $cn = $cns->post($draft, $this->cashBankId());
        $this->assertNotNull($cn->refund_payment_id);
        $this->assertMoney('10.500', $cn->refunded_amount);

        $this->get('/credit-notes/'.$cn->id)->assertOk()
            ->assertSee($cn->number)->assertSee($cn->refundPayment->number)->assertSee('مُرد للعميل')
            ->assertSee(route('vouchers.payment', $cn->refundPayment), false)->assertSee(route('credit-notes.print', [$cn, 'pdf']), false)
            ->assertSee('Hamad Al Rawahi')->assertSee('قيد يومية');
        $this->get('/credit-notes/'.$cn->id.'/print')->assertOk()
            ->assertSee('إشعار دائن / مرتجع مبيعات')->assertSee($cn->number)->assertSee($inv->number)
            ->assertSee('Wrong part ordered')->assertSee('10.500')->assertSee($cn->refundPayment->number)->assertDontSee('DRAFT');
        $this->get('/m/credit-notes')->assertOk()->assertSee('مسترد');
        $this->get('/invoices/'.$inv->id)->assertOk()->assertSee(route('credit-notes.print', $cn), false);
    }

    public function test_credit_note_without_refund_is_credited_to_the_account(): void
    {
        $wh = $this->mainWarehouse()->id;
        $part = $this->makePart(['sale_price' => 10]);
        app(StockService::class)->receive($part, $wh, 10, 4, ['type' => 'opening']);
        $svc = app(InvoiceService::class);
        $inv = $svc->post($svc->createDraft(['customer_id' => $this->makeCustomer()->id, 'warehouse_id' => $wh], [
            ['item_type' => 'part', 'part_id' => $part->id, 'qty' => 2, 'unit_price' => 10, 'tax_rate' => 5],
        ]));
        $cns = app(CreditNoteService::class);
        $cn = $cns->post($cns->create($inv, [$inv->items->first()->id => 1]));

        $this->assertNull($cn->refund_payment_id);
        $this->assertMoney('10.500', $cn->creditedToAccount());
        $this->get('/credit-notes/'.$cn->id)->assertOk()->assertSee('بدون رد نقدي');
        $this->get('/m/credit-notes')->assertOk()->assertSee('مقيد للحساب');
        $this->get('/credit-notes/'.$cn->id.'/print/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_print_requires_permission(): void
    {
        $inv = $this->paidInvoice();
        $cns = app(CreditNoteService::class);
        $cn = $cns->post($cns->create($inv, [$inv->items->first()->id => 1]));
        $this->actAsRole('Technician');
        $this->get('/credit-notes/'.$cn->id.'/print')->assertForbidden();
    }
}
