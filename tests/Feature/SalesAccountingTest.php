<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\FiscalPeriod;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\FiscalService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Models\CashRegister;
use App\Modules\Sales\Services\CreditNoteService;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Sales\Services\PaymentService;
use App\Modules\Sales\Services\PosService;
use Tests\TestCase;

class SalesAccountingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    /** Draft + post an invoice for 2 parts @ 20.000 (cost 10.000) and a 15.000 free-text service line. */
    protected function postedInvoice(array $customerAttrs = [], bool $credit = false)
    {
        $wh = $this->mainWarehouse()->id;
        $part = $this->makePart();
        app(StockService::class)->receive($part, $wh, 10, 10, ['type' => 'opening']);
        $customer = $this->makeCustomer($customerAttrs);
        $svc = app(InvoiceService::class);
        $inv = $svc->createDraft(['customer_id' => $customer->id, 'warehouse_id' => $wh, 'payment_terms' => $credit ? 'credit' : 'cash'], [
            ['item_type' => 'part', 'part_id' => $part->id, 'qty' => 2, 'unit_price' => 20, 'tax_rate' => 5],
            ['item_type' => 'service', 'description' => 'Labour', 'qty' => 1, 'unit_price' => 15, 'tax_rate' => 5],
        ]);

        return [$svc->post($inv), $part, $customer];
    }

    public function test_posting_an_invoice_updates_stock_and_books_a_balanced_entry(): void
    {
        [$inv, $part] = $this->postedInvoice();

        // 40 + 15 = 55 net, VAT 2.750, total 57.750
        $this->assertMoney('57.750', $inv->total);
        $this->assertSame('posted', $inv->status);
        $this->assertNotNull($inv->journal_entry_id);
        $this->assertMoney(8, StockLevel::query()->where('part_id', $part->id)->value('qty_on_hand'));

        $this->assertMoney('57.750', $this->balance('ar'));
        $this->assertMoney('-40.000', $this->balance('sales_parts'));
        $this->assertMoney('-15.000', $this->balance('sales_services'));
        $this->assertMoney('-2.750', $this->balance('vat_output'));
        $this->assertMoney('20.000', $this->balance('cogs'));
        $this->assertLedgerBalanced();

        // posted documents are locked
        $this->expectException(\Throwable::class);
        app(InvoiceService::class)->updateDraft($inv, [], []);
    }

    public function test_payment_allocation_and_cancellation(): void
    {
        [$inv, , $customer] = $this->postedInvoice();
        $payments = app(PaymentService::class);

        $p = $payments->receiveFromCustomer($customer, '50.000', $this->cashAccountId(), 'cash', [$inv->id => '50.000']);
        $this->assertSame('partially_paid', $inv->fresh()->status);
        $this->assertMoney('7.750', $this->balance('ar'));

        $payments->cancel($p->fresh(), 'test');
        $this->assertMoney('57.750', $this->balance('ar'));
        $this->assertMoney(0, $inv->fresh()->paid_amount);

        $payments->receiveFromCustomer($customer, '57.750', $this->cashAccountId(), 'cash');
        $this->assertSame('paid', $inv->fresh()->status);
        $this->assertMoney(0, $this->balance('ar'));
        $this->assertLedgerBalanced();
    }

    public function test_credit_note_restocks_and_reverses_revenue_and_cost(): void
    {
        [$inv, $part] = $this->postedInvoice();
        $item = $inv->items()->where('item_type', 'part')->first();

        $cns = app(CreditNoteService::class);
        $cn = $cns->post($cns->create($inv, [$item->id => 1], true, 'Wrong part'));

        $this->assertMoney('21.000', $cn->total); // 20 + 5% VAT
        $this->assertMoney(9, StockLevel::query()->where('part_id', $part->id)->value('qty_on_hand'));
        $this->assertMoney('10.000', $this->balance('cogs'));
        $this->assertMoney('36.750', $this->balance('ar'));
        $this->assertLedgerBalanced();

        // cannot return more than invoiced
        $this->expectException(BusinessException::class);
        $cns->create($inv->fresh(), [$item->id => 2]);
    }

    public function test_returns_use_the_discounted_price_charged_on_the_invoice(): void
    {
        $wh = $this->mainWarehouse()->id;
        $part = $this->makePart(['sale_price' => 10]);
        app(StockService::class)->receive($part, $wh, 10, 4, ['type' => 'opening']);
        $svc = app(InvoiceService::class);
        // 3 x 10.000 with a 3.000 line discount => charged 9.000 each, total 27.000 + 5% = 28.350
        $inv = $svc->post($svc->createDraft(['customer_id' => $this->makeCustomer()->id, 'warehouse_id' => $wh], [
            ['item_type' => 'part', 'part_id' => $part->id, 'qty' => 3, 'unit_price' => 10, 'discount' => 3, 'tax_rate' => 5],
        ]));
        $this->assertMoney('28.350', $inv->total);
        $item = $inv->items->first();
        $cns = app(CreditNoteService::class);

        // return 1 => 9.000 + 0.450 VAT, not the 10.000 list price
        $cn1 = $cns->post($cns->create($inv, [$item->id => 1]));
        $this->assertMoney('9.000', $cn1->items->first()->line_total);
        $this->assertMoney('1.000', $cn1->items->first()->discount);
        $this->assertMoney('9.450', $cn1->total);

        // returning the rest gives back exactly what was invoiced in total
        $cn2 = $cns->post($cns->create($inv->fresh(), [$item->id => 2]));
        $this->assertMoney('18.900', $cn2->total);
        $this->assertMoney('28.350', \App\Support\Money::add($cn1->total, $cn2->total));
        $this->assertMoney(0, $this->balance('ar'));
        $this->assertMoney(0, $this->balance('sales_parts'));
        $this->assertLedgerBalanced();

        // the return form shows the price charged
        $this->get('/invoices/'.$inv->id.'/return')->assertOk()->assertSee('9.000');
    }

    public function test_cost_centers_are_installed_and_used_for_parts_sales(): void
    {
        $this->assertSame(12, \App\Modules\Core\Models\CostCenter::query()->count());
        $newCc = \App\Modules\Core\Models\CostCenter::query()->where('code', 'CC-008')->value('id');
        $usedCc = \App\Modules\Core\Models\CostCenter::query()->where('code', 'CC-009')->value('id');
        $this->assertNotNull(\App\Modules\Workshop\Models\Department::query()->where('code', 'MECH')->value('cost_center_id'));

        $wh = $this->mainWarehouse()->id;
        $new = $this->makePart(['part_type' => 'genuine']);
        $used = $this->makePart(['part_type' => 'used']);
        app(StockService::class)->receive($new, $wh, 5, 4, ['type' => 'opening']);
        app(StockService::class)->receive($used, $wh, 5, 2, ['type' => 'opening']);
        $svc = app(InvoiceService::class);
        $inv = $svc->post($svc->createDraft(['customer_id' => $this->makeCustomer()->id, 'warehouse_id' => $wh], [
            ['item_type' => 'part', 'part_id' => $new->id, 'qty' => 1, 'unit_price' => 20, 'tax_rate' => 5],
            ['item_type' => 'part', 'part_id' => $used->id, 'qty' => 1, 'unit_price' => 8, 'tax_rate' => 5],
        ]));
        $lines = \App\Modules\Accounting\Models\JournalLine::query()->where('journal_entry_id', $inv->journal_entry_id)->get();
        $salesAcc = \App\Modules\Accounting\Models\Account::query()->where('system_key', 'sales_parts')->value('id');
        $this->assertMoney(20, $lines->where('account_id', $salesAcc)->where('cost_center_id', $newCc)->sum('credit'));
        $this->assertMoney(8, $lines->where('account_id', $salesAcc)->where('cost_center_id', $usedCc)->sum('credit'));
    }

    public function test_credit_limit_blocks_credit_invoice_for_users_without_override(): void
    {
        $this->actAsRole('Salesperson');
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessageMatches('/Credit limit exceeded/');
        $this->postedInvoice(['credit_limit' => 10], true);
    }

    public function test_journal_entries_must_balance_and_reversal_nets_to_zero(): void
    {
        $posting = app(PostingService::class);
        try {
            $posting->post(['date' => now()->toDateString(), 'description' => 'bad', 'lines' => [
                ['account' => 'cash', 'debit' => 10], ['account' => 'capital', 'credit' => 9],
            ]]);
            $this->fail('Unbalanced entry was accepted');
        } catch (BusinessException) {
        }

        $je = $posting->post(['date' => now()->toDateString(), 'description' => 'capital', 'lines' => [
            ['account' => 'cash', 'debit' => 100], ['account' => 'capital', 'credit' => 100],
        ]]);
        $posting->reverse($je, null, 'test');
        $this->assertMoney(0, $this->balance('cash'));
        $this->assertSame('reversed', $je->fresh()->status);
        $this->assertSame(2, JournalEntry::query()->count());
    }

    public function test_closed_period_rejects_postings(): void
    {
        FiscalService::ensureYearFor(now());
        $period = FiscalPeriod::query()->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->firstOrFail();
        FiscalService::closePeriod($period);

        $this->expectException(BusinessException::class);
        app(PostingService::class)->post(['date' => now()->toDateString(), 'description' => 'x', 'lines' => [
            ['account' => 'cash', 'debit' => 1], ['account' => 'capital', 'credit' => 1],
        ]]);
    }

    public function test_pos_checkout_split_payment_and_cash_short_on_close(): void
    {
        $part = $this->makePart(['sale_price' => 10]);
        $register = CashRegister::query()->firstOrFail();
        app(StockService::class)->receive($part, $register->warehouse_id, 5, 4, ['type' => 'opening']);
        $pos = app(PosService::class);
        $session = $pos->openSession($register, 20);

        $walkIn = (int) setting('pos.walk_in_customer_id');
        // 2 x 10 + 5% = 21.000 : 15 card + 10 cash (change 4)
        $r = $pos->checkout($session, ['customer_id' => $walkIn, 'items' => [['part_id' => $part->id, 'qty' => 2]]],
            [['method' => 'card', 'amount' => 15], ['method' => 'cash', 'amount' => 10]]);
        $this->assertSame('paid', $r['invoice']->status);
        $this->assertMoney(4, $r['change']);

        // expected drawer: 20 float + 6 cash kept; counted 25 => 1.000 short
        $this->assertMoney(26, $pos->expectedCash($session->fresh()));
        $pos->closeSession($session->fresh(), 25);
        $this->assertMoney(1, $this->balance('cash_short'));
        $this->assertLedgerBalanced();

        // walk-in customers cannot buy on credit
        $session2 = $pos->openSession($register, 0);
        $this->expectException(BusinessException::class);
        $pos->checkout($session2, ['customer_id' => $walkIn, 'items' => [['part_id' => $part->id, 'qty' => 1]]], []);
    }
}
