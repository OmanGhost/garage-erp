<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Modules\CRM\Models\Customer;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Loyalty\Models\GiftCard;
use App\Modules\Loyalty\Models\LoyaltyTransaction;
use App\Modules\Loyalty\Models\Membership;
use App\Modules\Loyalty\Models\MembershipPlan;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Modules\Loyalty\Services\MembershipService;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Services\CreditNoteService;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Sales\Services\PaymentService;
use App\Modules\Store\Models\Coupon;
use App\Modules\Workshop\Models\Service;
use Tests\TestCase;

class MembershipLoyaltyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    protected function invoice(Customer $c, array $lines, bool $post = true): Invoice
    {
        $svc = app(InvoiceService::class);
        $inv = $svc->createDraft(['customer_id' => $c->id, 'warehouse_id' => $this->mainWarehouse()->id, 'payment_terms' => 'credit'], $lines);

        return $post ? $svc->post($inv) : $inv;
    }

    public function test_membership_sale_activation_benefits_and_balance(): void
    {
        $oil = Service::create(['code' => 'OIL-01', 'name_ar' => 'تغيير زيت', 'price' => 20, 'tax_rate' => 5, 'is_active' => true]);
        $part = $this->makePart(['sale_price' => 50]);
        app(StockService::class)->receive($part, $this->mainWarehouse()->id, 10, 20, ['type' => 'opening']);
        $this->post('/m/membership-plans', ['code' => 'GOLD', 'name' => 'ذهبية', 'price' => 120, 'duration_months' => 12, 'parts_discount' => 10, 'labor_discount' => 0,
            'roadside_calls' => 2, 'is_active' => 1, '_idem' => 'mp-1', 'items_text' => "OIL-01 | 1"])->assertSessionHasNoErrors();
        $plan = MembershipPlan::query()->firstOrFail();
        $this->assertSame(1, $plan->items()->count());

        $c = $this->makeCustomer(['name' => 'Member One']);
        $this->post('/customers/'.$c->id.'/memberships', ['membership_plan_id' => $plan->id])->assertRedirect();
        $m = Membership::query()->firstOrFail();
        $this->assertSame('pending', $m->status);
        app(InvoiceService::class)->post($m->invoice);
        $m->refresh();
        $this->assertSame('active', $m->status);
        $this->assertSame(1, $m->balances()->first()->included);

        // a draft with the included oil change and a part: oil free, part 10 % off
        $inv = $this->invoice($c, [['item_type' => 'service', 'service_id' => $oil->id, 'description' => 'Oil', 'qty' => 1, 'unit_price' => 20, 'tax_rate' => 5],
            ['item_type' => 'part', 'part_id' => $part->id, 'qty' => 1, 'unit_price' => 50, 'tax_rate' => 5]], false);
        $this->get('/invoices/'.$inv->id)->assertOk()->assertSee(__('Apply membership benefits'));
        $this->post('/invoices/'.$inv->id.'/loyalty/membership')->assertSessionHas('success');
        $inv->refresh();
        $this->assertSame('25.000', $inv->discount_total);                // 20 + 5
        $this->assertSame('47.250', $inv->total);                         // 45 + 5 % VAT

        // a second draft also gets the oil free, but only one can be posted (balance re-checked under lock)
        $inv2 = $this->invoice($c, [['item_type' => 'service', 'service_id' => $oil->id, 'description' => 'Oil', 'qty' => 1, 'unit_price' => 20, 'tax_rate' => 5]], false);
        app(MembershipService::class)->applyToInvoice($inv2);
        app(InvoiceService::class)->post($inv);
        $this->assertSame(1, $m->balances()->first()->used);
        try {
            app(InvoiceService::class)->post($inv2->fresh());
            $this->fail('balance not re-checked');
        } catch (BusinessException) {
        }
        $this->get('/memberships/'.$m->id)->assertOk()->assertSee('تغيير زيت')->assertSee($inv->fresh()->displayNumber());
        $this->get('/memberships/'.$m->id.'/card')->assertOk();
        $this->assertLedgerBalanced();
    }

    public function test_points_earn_redeem_cancel_return_and_referral(): void
    {
        $referrer = $this->makeCustomer(['name' => 'Referrer']);
        $c = $this->makeCustomer(['name' => 'Loyal']);
        $c->update(['referred_by_id' => $referrer->id]);
        $inv = $this->invoice($c, [['item_type' => 'service', 'description' => 'Repair', 'qty' => 1, 'unit_price' => 300, 'tax_rate' => 5]]);
        $this->assertSame('300.000', (string) $c->fresh()->loyalty_points);           // 1 point / OMR before VAT
        $this->assertSame('200.000', (string) $referrer->fresh()->loyalty_points);    // referral bonus, once
        app(LoyaltyService::class)->earn($inv);                                       // idempotent
        $this->assertSame(1, LoyaltyTransaction::query()->where('type', 'earn')->count());
        $this->assertMoney(5, $this->balance('loyalty_liability') * -1);              // 500 points × 0.010

        // pay 2.000 with 200 points
        $this->post('/invoices/'.$inv->id.'/loyalty/points', ['points' => 50])->assertSessionHas('error');   // below the minimum
        $this->post('/invoices/'.$inv->id.'/loyalty/points', ['points' => 200])->assertSessionHas('success');
        $pay = Payment::query()->where('method', 'loyalty')->firstOrFail();
        $this->assertNull($pay->bank_account_id);
        $this->assertSame('2.000', $inv->fresh()->paid_amount);
        $this->assertSame('100.000', (string) $c->fresh()->loyalty_points);
        $this->get('/payments/'.$pay->id)->assertOk();

        // cancelling the receipt gives the points back
        app(PaymentService::class)->cancel($pay, 'test');
        $this->assertSame('300.000', (string) $c->fresh()->loyalty_points);
        $this->assertSame('0.000', $inv->fresh()->paid_amount);

        // a full return takes the earned points back
        $cns = app(CreditNoteService::class);
        $cns->post($cns->create($inv->fresh(), [$inv->items()->first()->id => 1], false));
        $this->assertSame('0.000', (string) $c->fresh()->loyalty_points);
        $this->get('/loyalty')->assertOk()->assertSee('Referrer');
        $this->assertLedgerBalanced();
    }

    public function test_gift_cards_coupons_and_offers(): void
    {
        $c = $this->makeCustomer(['name' => 'Gifted']);
        $this->post('/loyalty/gift-cards', ['amount' => 30, 'bank_account_id' => $this->cashAccountId(), 'recipient_name' => 'Sara'])->assertSessionHas('print_voucher');
        $card = GiftCard::query()->firstOrFail();
        $this->get('/gift-cards/'.$card->id.'/print')->assertOk()->assertSee($card->code);
        $inv = $this->invoice($c, [['item_type' => 'service', 'description' => 'Wash', 'qty' => 1, 'unit_price' => 20, 'tax_rate' => 5]]);
        $this->post('/invoices/'.$inv->id.'/loyalty/gift-card', ['code' => strtolower($card->code)])->assertSessionHas('success');
        $this->assertSame('9.000', $card->fresh()->balance);              // 30 − 21
        $this->assertSame('paid', $inv->fresh()->status);
        $this->post('/invoices/'.$inv->id.'/loyalty/gift-card', ['code' => 'GC-NONE-0000'])->assertSessionHas('error');

        // personal offer to an inactive customer, used once on an invoice
        Invoice::query()->whereKey($inv->id)->update(['issue_date' => now()->subDays(400)->toDateString()]);
        $this->post('/loyalty/offers', ['customers' => [$c->id], 'type' => 'percent', 'value' => 10, 'valid_days' => 30])->assertSessionHas('success');
        $coupon = Coupon::query()->where('customer_id', $c->id)->firstOrFail();
        $draft = $this->invoice($c, [['item_type' => 'service', 'description' => 'Polish', 'qty' => 1, 'unit_price' => 40, 'tax_rate' => 5]], false);
        $this->post('/invoices/'.$draft->id.'/loyalty/coupon', ['code' => $coupon->code])->assertSessionHas('success');
        $this->assertSame('4.000', $draft->fresh()->discount_total);
        app(InvoiceService::class)->post($draft->fresh());
        $this->assertSame(1, $coupon->fresh()->used_count);
        $other = $this->invoice($c, [['item_type' => 'service', 'description' => 'Polish', 'qty' => 1, 'unit_price' => 40, 'tax_rate' => 5]], false);
        $this->post('/invoices/'.$other->id.'/loyalty/coupon', ['code' => $coupon->code])->assertSessionHas('error');   // single use
        $this->assertLedgerBalanced();
    }

    public function test_features_follow_the_plan(): void
    {
        $this->company->update(['enabled_modules' => ['core', 'crm', 'vehicles', 'workshop', 'inventory', 'sales']]);
        tenant()->forget();
        tenant()->set($this->company->id, $this->company->branches()->value('id'));
        $this->get('/loyalty')->assertForbidden();
        $this->get('/m/membership-plans')->assertForbidden();
        $inv = $this->invoice($this->makeCustomer(), [['item_type' => 'service', 'description' => 'X', 'qty' => 1, 'unit_price' => 100, 'tax_rate' => 5]]);
        $this->assertSame(0, LoyaltyTransaction::query()->count());       // no points without the module
        $this->get('/invoices/'.$inv->id)->assertOk()->assertDontSee(__('Membership & rewards'));
    }
}
