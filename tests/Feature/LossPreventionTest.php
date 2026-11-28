<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Services\InventoryDocuments;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Loyalty\Models\Membership;
use App\Modules\Loyalty\Models\MembershipPlan;
use App\Modules\LossPrevention\Models\FraudAlert;
use App\Modules\LossPrevention\Services\LossPreventionMonitor;
use App\Modules\Sales\Models\CashRegister;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Sales\Services\PaymentService;
use App\Modules\Sales\Services\PosService;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\Service;
use App\Modules\Workshop\Services\JobCardService;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LossPreventionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-25 10:00:00');   // a Wednesday, inside working hours
        $this->makeTenant();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function alerts(string $rule)
    {
        return FraudAlert::query()->where('rule', $rule)->get();
    }

    protected function stockedPart(array $attrs = [])
    {
        $part = $this->makePart($attrs + ['sale_price' => 50]);
        app(StockService::class)->receive($part, $this->mainWarehouse()->id, 20, 40, ['type' => 'opening']);

        return $part;
    }

    protected function sell(array $lines, array $header = []): Invoice
    {
        $svc = app(InvoiceService::class);

        return $svc->post($svc->createDraft($header + ['customer_id' => $this->makeCustomer()->id, 'warehouse_id' => $this->mainWarehouse()->id, 'payment_terms' => 'credit'], $lines));
    }

    public function test_discount_below_cost_and_price_override_rules(): void
    {
        $part = $this->stockedPart(['wholesale_price' => 42]);

        // 40 % manual discount, sold for 30 against a cost of 40
        $inv = $this->sell([['item_type' => 'part', 'part_id' => $part->id, 'qty' => 1, 'unit_price' => 50, 'discount' => 20, 'tax_rate' => 5]]);
        $d = $this->alerts('high_discount')->first();
        $this->assertNotNull($d);
        $this->assertSame('high', $d->severity);
        $this->assertSame($inv->id, (int) $d->subject_id);
        $this->assertSame('10.000', (string) $this->alerts('below_cost')->first()->amount);

        // 10 % is inside the limit; a wholesale invoice at the wholesale price is not an override
        $this->sell([['item_type' => 'part', 'part_id' => $part->id, 'qty' => 1, 'unit_price' => 50, 'discount' => 5, 'tax_rate' => 5]]);
        $this->sell([['item_type' => 'part', 'part_id' => $part->id, 'qty' => 1, 'unit_price' => 42, 'tax_rate' => 5]], ['price_level' => 'wholesale']);
        $this->assertCount(1, $this->alerts('high_discount'));
        $this->assertCount(0, $this->alerts('price_override'));

        // typed far below the retail list price
        $this->sell([['item_type' => 'part', 'part_id' => $part->id, 'qty' => 1, 'unit_price' => 30, 'tax_rate' => 5]], ['price_level' => 'retail']);
        $this->assertCount(1, $this->alerts('price_override'));

        // services included in a membership are free by design
        $svc = Service::create(['code' => 'OIL', 'name_ar' => 'زيت', 'price' => 20, 'tax_rate' => 5, 'is_active' => true]);
        $c = $this->makeCustomer();
        $plan = MembershipPlan::create(['code' => 'P', 'name' => 'P', 'price' => 50, 'duration_months' => 12, 'parts_discount' => 0, 'labor_discount' => 0, 'roadside_calls' => 0, 'is_active' => true]);
        $m = Membership::create(['number' => 'MEM-X', 'customer_id' => $c->id, 'membership_plan_id' => $plan->id, 'price' => 50, 'status' => 'active', 'starts_at' => today()->subDay(), 'ends_at' => today()->addYear()]);
        $draft = app(InvoiceService::class)->createDraft(['customer_id' => $c->id, 'payment_terms' => 'credit'],
            [['item_type' => 'service', 'service_id' => $svc->id, 'description' => 'Oil', 'qty' => 1, 'unit_price' => 20, 'discount' => 20, 'tax_rate' => 5],
             ['item_type' => 'labor', 'description' => 'Wash', 'qty' => 1, 'unit_price' => 5, 'tax_rate' => 5]]);
        $draft->items()->where('item_type', 'service')->update(['membership_id' => $m->id]);
        $draft->forceFill(['membership_id' => $m->id])->save();
        app(InvoiceService::class)->post($draft->fresh());
        $this->assertCount(1, $this->alerts('high_discount'));
        $this->assertCount(0, $this->alerts('after_hours'));
    }

    public function test_cancelled_receipt_and_voided_pos_sale(): void
    {
        $c = $this->makeCustomer();
        $p = app(PaymentService::class)->receiveFromCustomer($c, 150, $this->cashAccountId(), 'cash');
        app(PaymentService::class)->cancel($p, 'Customer changed mind');
        $a = $this->alerts('payment_cancelled')->first();
        $this->assertSame('high', $a->severity);                       // cash and above the large amount
        $this->assertSame('Customer changed mind', $a->details['reason']);

        $part = $this->stockedPart();
        $session = app(PosService::class)->openSession(CashRegister::query()->firstOrFail(), 0);
        $held = app(PosService::class)->hold($session, ['customer_id' => (int) setting('pos.walk_in_customer_id'), 'items' => [['part_id' => $part->id, 'qty' => 2]]]);
        $held->items()->delete();
        $held->delete();
        $this->assertCount(1, $this->alerts('void_sale'));
    }

    public function test_returns_and_price_cuts(): void
    {
        $part = $this->stockedPart();
        $inv = $this->sell([['item_type' => 'part', 'part_id' => $part->id, 'qty' => 3, 'unit_price' => 50, 'tax_rate' => 5]]);
        $cns = app(\App\Modules\Sales\Services\CreditNoteService::class);
        $cns->post($cns->create($inv, [$inv->items()->first()->id => 1], true, 'Wrong part'));
        $cn = $this->alerts('credit_note')->first();
        $this->assertNotNull($cn);
        $this->assertSame($inv->number, $cn->details['invoice']);
        $this->assertTrue($cn->details['same_day']);
        $this->assertSame('medium', $cn->severity);

        $part->update(['sale_price' => 30]);
        $other = $this->stockedPart();
        $other->update(['sale_price' => 45]);                            // −10 %: inside the limit
        $other->update(['sale_price' => 20]);
        $pc = $this->alerts('price_change');
        $this->assertCount(1, $pc);                                      // one alert per user and day
        $this->assertCount(2, $pc->first()->details['actions']);

        // standing customer discounts are not manual discounts
        $vip = $this->makeCustomer(['discount_percent' => 20]);
        app(InvoiceService::class)->post(app(InvoiceService::class)->createDraft(['customer_id' => $vip->id, 'warehouse_id' => $this->mainWarehouse()->id, 'payment_terms' => 'credit'],
            [['item_type' => 'part', 'part_id' => $part->id, 'qty' => 1, 'unit_price' => 60, 'discount' => 12, 'tax_rate' => 5]]));
        $this->assertCount(0, $this->alerts('high_discount'));
    }

    public function test_write_offs_and_repeated_adjustments_are_aggregated(): void
    {
        $part = $this->stockedPart();
        for ($i = 1; $i <= 3; $i++) {
            $adj = StockAdjustment::create(['number' => DocumentNumber::next('stock_adjustment'), 'warehouse_id' => $this->mainWarehouse()->id, 'date' => today(), 'reason' => 'loss', 'status' => 'draft']);
            $adj->items()->create(['part_id' => $part->id, 'qty' => -1, 'unit_cost' => 0]);
            app(InventoryDocuments::class)->postAdjustment($adj);
        }
        $this->assertCount(3, $this->alerts('stock_write_off'));            // 40.000 each, over the 20 minimum
        $freq = $this->alerts('frequent_adjustments');
        $this->assertCount(1, $freq);
        $this->assertSame(3, $freq->first()->occurrences);
        $this->assertCount(1, $this->alerts('part_adjustments'));

        // a counted stock take is expected to adjust and raises nothing
        $count = app(InventoryDocuments::class)->createCount($this->mainWarehouse()->id);
        app(InventoryDocuments::class)->postCount($count);
        $this->assertCount(3, $this->alerts('stock_write_off'));
    }

    public function test_parts_not_invoiced_when_the_vehicle_leaves(): void
    {
        $part = $this->stockedPart();
        $jobs = app(JobCardService::class);
        foreach (['repair', 'internal'] as $type) {
            $c = $this->makeCustomer();
            $v = Vehicle::create(['customer_id' => $c->id, 'plate_number' => (string) random_int(10000, 99999), 'vehicle_type' => 'car']);
            $jc = $jobs->create(['customer_id' => $c->id, 'vehicle_id' => $v->id, 'job_type' => $type]);
            $jcp = $jobs->requestPart($jc, ['part_id' => $part->id, 'qty' => 2, 'warehouse_id' => $this->mainWarehouse()->id]);
            $jobs->changeStatus($jc->fresh(), JobCard::APPROVED, null, true);
            $jobs->issuePart($jcp);
            if ($type === 'repair') {    // invoiced for labour only
                app(InvoiceService::class)->post(app(InvoiceService::class)->createDraft(['customer_id' => $c->id, 'job_card_id' => $jc->id, 'payment_terms' => 'credit'],
                    [['item_type' => 'labor', 'description' => 'Labour', 'qty' => 1, 'unit_price' => 10, 'tax_rate' => 5]]));
            }
            $jobs->changeStatus($jc->fresh(), JobCard::DELIVERED, null, true);
        }
        $alerts = $this->alerts('unbilled_parts');
        $this->assertCount(1, $alerts);                                  // the internal job is exempt
        $this->assertSame('80.000', (string) $alerts->first()->amount);
    }

    public function test_cash_shortage_escalates_when_repeated(): void
    {
        $pos = app(PosService::class);
        $register = CashRegister::query()->firstOrFail();
        foreach ([1, 2] as $n) {
            $s = $pos->openSession($register, 10);
            $pos->closeSession($s, 5, null, 'Missing');
        }
        $alerts = $this->alerts('cash_shortage')->sortBy('id')->values();
        $this->assertCount(2, $alerts);
        $this->assertSame('medium', $alerts[0]->severity);
        $this->assertSame('high', $alerts[1]->severity);
        $this->assertSame('Missing', $alerts[1]->details['reason']);
    }

    public function test_after_hours_only_for_staff_actions(): void
    {
        Carbon::setTestNow('2026-11-25 23:30:00');
        $c = $this->makeCustomer();
        app(PaymentService::class)->receiveFromCustomer($c, 5, $this->cashAccountId(), 'cash');
        app(PaymentService::class)->receiveFromCustomer($c, 6, $this->cashAccountId(), 'cash');
        $a = $this->alerts('after_hours');
        $this->assertCount(1, $a);                                        // one alert per user and day
        $this->assertSame(2, $a->first()->occurrences);

        auth()->logout();                                                 // scheduled job / no staff user
        Carbon::setTestNow('2026-11-26 23:30:00');
        AuditLogger::log('payment.received', null, []);
        $this->assertCount(1, $this->alerts('after_hours'));
    }

    public function test_rollback_raises_nothing_and_a_failing_rule_never_breaks_the_operation(): void
    {
        $part = $this->stockedPart();
        try {
            DB::transaction(function () use ($part) {
                $this->sell([['item_type' => 'part', 'part_id' => $part->id, 'qty' => 1, 'unit_price' => 50, 'discount' => 30, 'tax_rate' => 5]]);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertCount(0, $this->alerts('high_discount'));

        app()->instance(LossPreventionMonitor::class, new class extends LossPreventionMonitor
        {
            protected function inspect(array $c): void
            {
                throw new \RuntimeException('rule bug');
            }
        });
        $inv = $this->sell([['item_type' => 'part', 'part_id' => $part->id, 'qty' => 1, 'unit_price' => 50, 'discount' => 30, 'tax_rate' => 5]]);
        $this->assertSame('posted', $inv->fresh()->status);
    }

    public function test_audit_log_is_sealed_and_cannot_be_changed_by_the_app(): void
    {
        AuditLogger::log('test.event', null, ['x' => 'قيمة']);
        $this->assertSame([], AuditLogger::verify($this->company->id)['tampered']);
        $row = AuditLog::query()->where('event', 'test.event')->firstOrFail();
        $this->assertNotNull($row->seal);

        try {
            $row->update(['event' => 'changed']);
            $this->fail('audit row updated');
        } catch (BusinessException) {
        }
        try {
            $row->delete();
            $this->fail('audit row deleted');
        } catch (BusinessException) {
        }

        DB::table('audit_logs')->where('id', $row->id)->update(['new_values' => '{"x":"other"}']);
        DB::table('audit_logs')->insert(['company_id' => $this->company->id, 'event' => 'legacy', 'created_at' => now()]);
        $r = AuditLogger::verify($this->company->id);
        $this->assertSame([$row->id], $r['tampered']);
        $this->assertSame(1, $r['unsealed']);

        $this->post('/loss-prevention/verify-audit')->assertSessionHas('error');
    }

    public function test_review_screens_and_segregation_of_duties(): void
    {
        $admin = auth()->user();
        $c = $this->makeCustomer();
        app(PaymentService::class)->cancel(app(PaymentService::class)->receiveFromCustomer($c, 20, $this->cashAccountId(), 'cash'), 'Mistake');
        $a = $this->alerts('payment_cancelled')->first();

        $this->get('/loss-prevention')->assertOk()->assertSee($a->title);
        $this->get('/loss-prevention/'.$a->id)->assertOk()->assertSee(__('This alert is about your own action; another manager must review it.'));
        $this->post('/loss-prevention/'.$a->id.'/review', ['status' => 'cleared', 'review_notes' => 'I did it'])->assertSessionHas('error');
        $this->assertSame('unreviewed', $a->fresh()->status);

        $gm = $this->actAsRole('General Manager');
        $this->post('/loss-prevention/'.$a->id.'/review', ['status' => 'cleared'])->assertSessionHasErrors('review_notes');
        $this->post('/loss-prevention/'.$a->id.'/review', ['status' => 'substantiated', 'review_notes' => 'Cash not returned to customer'])->assertRedirect();
        $a->refresh();
        $this->assertSame('substantiated', $a->status);
        $this->assertSame($gm->id, (int) $a->reviewed_by);

        $this->actAsRole('Cashier');
        $this->get('/loss-prevention')->assertForbidden();
        $this->actingAs($admin);
        $this->post('/loss-prevention/settings', ['max_discount_percent' => 25, 'price_override_percent' => 10, 'price_drop_percent' => 20, 'large_amount' => 100,
            'write_off_min' => 20, 'adjustments_per_week' => 3, 'part_adjustments_30d' => 3, 'cash_difference_min' => 1, 'after_hours_grace_minutes' => 30])->assertSessionHas('success');
        $this->assertSame('25', setting('fraud.max_discount_percent'));
    }

    public function test_module_follows_the_plan(): void
    {
        $this->company->update(['enabled_modules' => ['core', 'crm', 'vehicles', 'workshop', 'inventory', 'sales']]);
        tenant()->forget();
        tenant()->set($this->company->id, $this->company->branches()->value('id'));
        $this->get('/loss-prevention')->assertForbidden();
        $p = app(PaymentService::class)->receiveFromCustomer($this->makeCustomer(), 150, $this->cashAccountId(), 'cash');
        app(PaymentService::class)->cancel($p, 'x');
        $this->assertSame(0, FraudAlert::query()->count());
    }
}
