<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Modules\Loyalty\Models\Membership;
use App\Modules\Loyalty\Models\MembershipPlan;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Models\SupplierInvoice;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\RoadsideRequest;
use App\Modules\Workshop\Models\SubcontractOrder;
use App\Modules\Workshop\Services\CapacityService;
use App\Modules\Workshop\Services\JobCardService;
use App\Modules\Workshop\Services\RoadsideService;
use App\Support\Settings;
use Tests\TestCase;

class SubcontractRoadsideTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    protected function vehicle(?int $customerId = null): Vehicle
    {
        return Vehicle::create(['customer_id' => $customerId ?? $this->makeCustomer()->id, 'plate_number' => (string) random_int(10000, 99999), 'plate_code' => 'A', 'vehicle_type' => 'car']);
    }

    protected function approvedJob(): JobCard
    {
        $v = $this->vehicle();
        $svc = app(JobCardService::class);
        $jc = $svc->create(['customer_id' => $v->customer_id, 'vehicle_id' => $v->id, 'customer_complaint' => 'Engine noise']);
        $svc->addTask($jc, ['description' => 'Diagnosis', 'unit_price' => 10, 'estimated_minutes' => 30]);

        return $svc->changeStatus($jc, JobCard::APPROVED, null, true);
    }

    public function test_subcontract_lifecycle_costs_the_job_and_prepares_the_supplier_invoice(): void
    {
        $jc = $this->approvedJob();
        $supplier = Supplier::create(['code' => 'S-ENG', 'name' => 'Muscat Engine Machining']);

        $this->post('/job-cards/'.$jc->id.'/subcontracts', ['supplier_id' => $supplier->id, 'work_type' => 'machining', 'description' => 'Skim cylinder head',
            'item_sent' => 'Cylinder head', 'quoted_cost' => 40, 'sell_price' => 65, 'expected_at' => today()->addDays(3)->toDateString(), 'warranty_days' => 90])
            ->assertSessionHas('success');
        $o = SubcontractOrder::query()->firstOrFail();
        $task = $o->task;
        $this->assertTrue($task->is_subcontract);
        $this->assertSame('pending', $task->approval_status);            // additional work on an approved job needs the customer's OK
        $this->assertSame('65.000', (string) $task->unit_price);

        // not sent before the customer approves; never auto-scheduled; cannot be started by a technician
        $this->post('/subcontracts/'.$o->id.'/send')->assertSessionHas('error');
        $task->update(['approval_status' => 'approved']);
        app(CapacityService::class)->autoSchedule($jc->fresh());
        $this->assertNull($task->fresh()->scheduled_start);
        $this->assertNotNull($jc->tasks()->where('is_subcontract', false)->first()->scheduled_start);
        try {
            app(JobCardService::class)->startTask($task->fresh());
            $this->fail('subcontract task started');
        } catch (BusinessException) {
        }

        $this->post('/subcontracts/'.$o->id.'/send')->assertSessionHas('success');
        $this->assertSame('sent_out', $o->fresh()->status);
        $this->assertSame('40.000', (string) $jc->fresh()->subcontract_cost);   // the quote while it is out

        // the vehicle cannot be made ready while its part is with the subcontractor
        $own = $jc->tasks()->where('is_subcontract', false)->first();
        $own->update(['status' => 'done']);
        try {
            app(JobCardService::class)->changeStatus($jc->fresh(), JobCard::READY);
            $this->fail('ready while work is out');
        } catch (BusinessException $e) {
            $this->assertStringContainsString(__('Outside work is still with the subcontractor. Receive it back first.'), $e->getMessage());
        }

        $this->get('/workshop/subcontracts')->assertOk()->assertSee($o->number);
        $this->get('/job-cards/'.$jc->id)->assertOk()->assertSee($o->number);

        $this->post('/subcontracts/'.$o->id.'/receive', ['actual_cost' => 45, 'supplier_invoice_number' => 'ME-778', 'tax_rate' => 5])->assertSessionHas('success');
        $o->refresh();
        $this->assertSame('back_in', $o->status);
        $this->assertSame(today()->addDays(90)->toDateString(), $o->warranty_until->toDateString());
        $this->assertSame('done', $o->task->status);
        $jc->refresh();
        $this->assertSame('45.000', (string) $jc->subcontract_cost);
        $this->assertSame('45.000', (string) $jc->total_cost);                 // no labour / parts cost recorded

        $si = SupplierInvoice::query()->findOrFail($o->supplier_invoice_id);
        $this->assertSame('draft', $si->status);
        $this->assertSame('47.250', (string) $si->total);
        app(PurchasingService::class)->postSupplierInvoice($si);
        $this->assertMoney('45.000', $this->balance('subcontract_cost'));
        $this->assertLedgerBalanced();

        app(JobCardService::class)->changeStatus($jc->fresh(), JobCard::READY);
        $this->assertSame(JobCard::READY, $jc->fresh()->status);
        $this->get('/subcontracts/'.$o->id.'/print')->assertOk()->assertSee('Cylinder head');
    }

    public function test_cancelling_a_subcontract_cancels_its_task(): void
    {
        $jc = $this->approvedJob();
        $o = app(\App\Modules\Workshop\Services\SubcontractService::class)->create($jc, ['supplier_id' => Supplier::create(['code' => 'S-2', 'name' => 'ECU Lab'])->id,
            'work_type' => 'ecu', 'description' => 'ECU repair', 'quoted_cost' => 30, 'sell_price' => 50, 'pre_approved' => true]);
        $this->post('/subcontracts/'.$o->id.'/send')->assertSessionHas('success');
        try {
            app(JobCardService::class)->cancelTask($o->task);            // only through the order
            $this->fail('task cancelled directly');
        } catch (BusinessException) {
        }
        $this->post('/subcontracts/'.$o->id.'/cancel', ['reason' => 'Not repairable'])->assertSessionHas('success');
        $this->assertSame('cancelled', $o->fresh()->status);
        $this->assertSame('cancelled', $o->task->fresh()->status);
        $this->assertSame('0.000', (string) $jc->fresh()->subcontract_cost);
    }

    public function test_roadside_call_dispatch_public_link_and_invoice(): void
    {
        Settings::set(['roadside.base_tow' => '25', 'roadside.km_rate' => '0.5', 'roadside.free_km' => '10']);
        $c = $this->makeCustomer(['name' => 'Salim Al Harthy', 'phone' => '+96899112233']);
        $v = $this->vehicle($c->id);
        $driver = $this->actAsRole('Technician');
        $this->actingAs(\App\Models\User::query()->where('email', 'admin@acme.test')->first());

        $this->post('/roadside', ['caller_name' => 'Salim', 'caller_phone' => '+96899112233', 'plate' => $v->plate_number, 'service_type' => 'tow',
            'location_text' => 'Muscat Expressway exit 7'])->assertRedirect();
        $r = RoadsideRequest::query()->firstOrFail();
        $this->assertSame($c->id, $r->customer_id);                        // matched by phone
        $this->assertSame($v->id, $r->vehicle_id);                          // and plate
        $this->assertSame('25.000', (string) $r->base_fee);
        $this->assertGreaterThanOrEqual(40, strlen($r->public_token));

        $this->post('/roadside/'.$r->id.'/assign', ['assigned_to' => $driver->id])->assertSessionHas('success');
        $this->post('/roadside/'.$r->id.'/on_site')->assertSessionHas('success');
        $r->refresh();
        $this->assertSame('on_site', $r->status);
        $this->assertNotNull($r->assigned_at);
        $this->assertNotNull($r->en_route_at);
        $this->assertNotNull($r->arrived_at);
        $this->get('/roadside/'.$r->id)->assertOk()->assertSee($r->number);
        $this->get('/roadside')->assertOk()->assertSee($r->number);

        // public link: bad token 404, coordinates validated, first name of the driver only
        $this->post('/logout');
        $this->get('/rsa/'.str_repeat('x', 48))->assertNotFound();
        $this->get('/rsa/short')->assertNotFound();
        $this->get('/rsa/'.$r->public_token)->assertOk()->assertSee($r->number)->assertDontSee('+96899112233');
        $this->postJson('/rsa/'.$r->public_token.'/location', ['lat' => 123, 'lng' => 58.4])->assertStatus(422);
        $this->postJson('/rsa/'.$r->public_token.'/location', ['lat' => 23.5880, 'lng' => 58.3829])->assertOk();
        $this->assertEqualsWithDelta(23.588, RoadsideRequest::query()->withoutGlobalScopes()->find($r->id)->lat, 0.0001);

        $this->enterTenant($this->company, 'admin@acme.test');
        $this->post('/roadside/'.$r->id.'/complete', ['distance_km' => 30, 'extra_charges' => 2, 'to_workshop' => 1])->assertRedirect();
        $r->refresh();
        $this->assertSame('completed', $r->status);
        $this->assertSame('37.000', (string) $r->total);                   // 25 + (30 − 10) × 0.5 + 2
        $inv = Invoice::query()->findOrFail($r->invoice_id);
        $this->assertSame('37.000', (string) $inv->subtotal);
        app(InvoiceService::class)->post($inv);
        $this->assertMoney('-37.000', $this->balance('sales_services'));
        $this->assertLedgerBalanced();
        $jc = JobCard::query()->findOrFail($r->job_card_id);                // towed into the workshop
        $this->assertSame($v->id, $jc->vehicle_id);

        // closed: the public link no longer accepts locations
        $this->postJson('/rsa/'.$r->public_token.'/location', ['lat' => 23.6, 'lng' => 58.4])->assertStatus(422);
    }

    public function test_membership_roadside_call_covers_the_base_fee_once(): void
    {
        Settings::set(['roadside.base_battery' => '10', 'roadside.free_km' => '10']);
        $c = $this->makeCustomer(['phone' => '+96891000001']);
        $plan = MembershipPlan::create(['code' => 'RSA', 'name' => 'Road', 'price' => 50, 'duration_months' => 12, 'parts_discount' => 0, 'labor_discount' => 0, 'roadside_calls' => 1, 'is_active' => true]);
        $m = Membership::create(['number' => 'MEM-T1', 'customer_id' => $c->id, 'membership_plan_id' => $plan->id, 'price' => 50, 'status' => 'active',
            'starts_at' => today()->subDay(), 'ends_at' => today()->addYear()]);
        $svc = app(RoadsideService::class);
        $driver = \App\Models\User::query()->where('email', 'admin@acme.test')->first();

        $r = $svc->create(['caller_name' => 'A', 'caller_phone' => '+96891000001', 'service_type' => 'battery']);
        $this->assertSame($m->id, $r->membership_id);
        $svc->assign($r, $driver->id);
        $out = $svc->complete($r->fresh(), ['distance_km' => 5, 'use_membership' => 1]);
        $this->assertTrue($out['request']->covered);
        $this->assertNull($out['invoice']);                                 // fully covered, nothing to bill
        $this->assertSame(1, (int) $m->fresh()->roadside_used);

        // completing again is refused; the membership is not charged twice
        try {
            $svc->complete($r->fresh(), ['distance_km' => 5, 'use_membership' => 1]);
            $this->fail('completed twice');
        } catch (BusinessException) {
        }
        $this->assertSame(1, (int) $m->fresh()->roadside_used);

        // no calls left: a new request is not linked to the membership; a cancelled one consumes nothing
        $r2 = $svc->create(['caller_name' => 'A', 'caller_phone' => '+96891000001', 'service_type' => 'battery']);
        $this->assertNull($r2->membership_id);
        $svc->cancel($r2, 'Solved by phone');
        $this->assertSame(1, (int) $m->fresh()->roadside_used);
    }

    public function test_features_follow_the_plan(): void
    {
        $this->company->update(['enabled_modules' => ['core', 'crm', 'vehicles', 'workshop', 'inventory', 'sales']]);
        tenant()->forget();
        tenant()->set($this->company->id, $this->company->branches()->value('id'));
        $this->get('/roadside')->assertForbidden();
        $this->get('/workshop/subcontracts')->assertForbidden();
    }
}
