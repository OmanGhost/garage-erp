<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\Complaint;
use App\Modules\CRM\Models\ServiceReminder;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Vehicles\Models\Vehicle;
use Tests\TestCase;

class TrackingFeaturesTest extends TestCase
{
    protected \App\Modules\Core\Models\Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = $this->makeTenant();
        $this->app->setLocale('ar');
    }

    protected function inTenant(callable $fn): mixed
    {
        return tenant()->run($this->company->id, $fn);
    }

    public function test_goods_receipt_can_be_printed(): void
    {
        $grn = $this->inTenant(function () {
            $supplier = \App\Modules\Purchasing\Models\Supplier::create(['code' => 'S-1', 'name' => 'Muscat Parts LLC', 'phone' => '+96824000000']);
            $part = $this->makePart(['sku' => 'GRN-PART-1']);
            $po = app(\App\Modules\Purchasing\Services\PurchasingService::class);

            return \App\Modules\Purchasing\Models\GoodsReceipt::query()->first() ?? $this->receiveDirect($supplier, $part);
        });
        $this->get('/goods-receipts/'.$grn->id.'/print')->assertOk()->assertSee('سند استلام بضاعة')->assertSee($grn->number)->assertSee('Muscat Parts LLC')->assertSee('GRN-PART-1');
        $this->get('/goods-receipts/'.$grn->id.'/print/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/goods-receipts/'.$grn->id)->assertOk()->assertSee(route('goods-receipts.print', $grn), false);
        $this->get('/m/goods-receipts')->assertOk()->assertSee(route('goods-receipts.print', $grn), false);
    }

    protected function receiveDirect($supplier, $part)
    {
        $grn = \App\Modules\Purchasing\Models\GoodsReceipt::create([
            'number' => 'GRN-TEST-1', 'supplier_id' => $supplier->id, 'warehouse_id' => $this->mainWarehouse()->id,
            'received_at' => now(), 'status' => 'posted', 'total' => 40, 'created_by' => auth()->id(),
        ]);
        $grn->items()->create(['part_id' => $part->id, 'qty' => 4, 'unit_cost' => 10, 'line_total' => 40]);

        return $grn;
    }

    public function test_complaint_gets_a_reference_tracking_and_print(): void
    {
        $customer = $this->makeCustomer(['name' => 'Aisha Al Lawati']);
        $this->post('/m/complaints', ['customer_id' => $customer->id, 'subject' => 'Car returned dirty', 'description' => 'Seats were dirty', 'priority' => 'high', 'status' => 'open', 'source' => 'phone'])
            ->assertRedirect();
        $c = $this->inTenant(fn () => Complaint::query()->firstOrFail());
        $this->assertMatchesRegularExpression('/^CMP-\d{4}-\d{6}$/', $c->number);

        $this->get('/m/complaints')->assertOk()->assertSee($c->number);
        $this->get('/complaints/'.$c->id)->assertOk()->assertSee($c->number)->assertSee('تم تسجيل الشكوى');

        // closing needs a resolution
        $this->from('/complaints/'.$c->id)->post('/complaints/'.$c->id.'/follow-up', ['status' => 'closed'])->assertSessionHasErrors('resolution');
        $this->post('/complaints/'.$c->id.'/follow-up', ['status' => 'in_progress', 'assigned_to' => auth()->id(), 'note' => 'Called the customer'])->assertSessionHas('success');
        $this->post('/complaints/'.$c->id.'/follow-up', ['status' => 'resolved', 'resolution' => 'Free interior cleaning'])->assertSessionHas('success');

        $c->refresh();
        $this->assertNotNull($c->resolved_at);
        $types = $this->inTenant(fn () => $c->updates()->pluck('type')->sort()->values()->all());
        $this->assertSame(['assigned', 'created', 'note', 'resolution', 'status', 'status'], $types);

        $this->get('/complaints/'.$c->id)->assertOk()->assertSee('Called the customer')->assertSee('Free interior cleaning');
        $this->get('/complaints/'.$c->id.'/print')->assertOk()->assertSee('نموذج شكوى')->assertSee($c->number)->assertSee('Aisha Al Lawati')->assertSee('Called the customer');
        $this->get('/complaints/'.$c->id.'/print/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_service_reminder_tracking_contacts_and_booking(): void
    {
        $customer = $this->makeCustomer(['name' => 'Hilal Al Siyabi', 'phone' => '+96891234567']);
        $r = $this->inTenant(function () use ($customer) {
            $v = Vehicle::create(['customer_id' => $customer->id, 'plate_number' => '5566', 'plate_code' => 'KM', 'plate_country' => 'OM', 'vehicle_type' => 'car']);

            return ServiceReminder::create(['customer_id' => $customer->id, 'vehicle_id' => $v->id, 'title' => 'Oil change', 'due_date' => now()->subDays(2)->toDateString(), 'status' => 'scheduled']);
        });

        $this->get('/reminders')->assertOk()->assertSee('Hilal Al Siyabi')->assertSee('متأخر')->assertSee('wa.me/96891234567', false);
        $this->get('/m/service-reminders')->assertOk(); // the classic list is still available

        // no SMS gateway configured: nothing is sent and the user is told so
        $this->post('/reminders/'.$r->id.'/send')->assertSessionHas('warning');

        // manual call: no answer, then the customer confirms
        $this->post('/reminders/'.$r->id.'/contact', ['channel' => 'call', 'outcome' => 'no_answer', 'follow_up_on' => now()->addDay()->toDateString()])->assertSessionHas('success');
        $this->post('/reminders/'.$r->id.'/contact', ['channel' => 'whatsapp', 'outcome' => 'acknowledged', 'notes' => 'Will come Sunday'])->assertSessionHas('success');
        $r->refresh();
        $this->assertNotNull($r->acknowledged_at);
        $this->assertSame(2, $r->contact_attempts);
        $this->assertSame('acknowledged', $r->stage());

        // booking from the reminder links the appointment
        $this->post('/reminders/'.$r->id.'/book', ['starts_at' => now()->addDays(3)->setTime(10, 0)->format('Y-m-d H:i'), 'duration' => 60])->assertSessionHas('success');
        $r->refresh();
        $this->assertNotNull($r->appointment_id);
        $this->assertSame('booked', $r->stage());
        $this->get('/reminders/'.$r->id)->assertOk()->assertSee('Will come Sunday')->assertSee('لا يرد')->assertSee($r->appointment->number);
        $this->get('/reminders?stage=booked')->assertOk()->assertSee('Hilal Al Siyabi');
    }
}
