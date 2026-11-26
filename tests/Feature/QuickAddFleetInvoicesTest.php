<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\Customer;
use App\Modules\Dismantling\Models\DismantledPart;
use App\Modules\Dismantling\Models\DismantledVehicle;
use App\Modules\Dismantling\Services\DismantlingService;
use App\Modules\Fleet\Models\Fleet;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Vehicles\Models\VehicleMake;
use App\Modules\Vehicles\Models\VehicleModel;
use App\Modules\Warranty\Models\Warranty;
use Tests\TestCase;

class QuickAddFleetInvoicesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    /* ---------------- quick add from search fields ---------------- */

    public function test_quick_add_customer_vehicle_part_service_supplier(): void
    {
        $this->get('/quick/customers?q=Salim')->assertOk()->assertSee('data-quick-form', false)->assertSee('value="Salim"', false);
        $j = $this->postJson('/quick/customers', ['type' => 'individual', 'name' => 'Salim Al Harthy', 'phone' => '91234567'])->assertOk()->json();
        $c = Customer::query()->findOrFail($j['item']['id']);
        $this->assertTrue($c->is_active);                     // switches not in the dialog keep their defaults
        $this->assertSame('retail', $c->price_level);
        $this->assertStringContainsString('Salim Al Harthy', $j['item']['text']);
        $this->postJson('/quick/customers', ['type' => 'individual', 'name' => ''])->assertStatus(422)->assertJsonValidationErrors(['name', 'phone']);

        // vehicle dialog is prefilled with the customer of the form it was opened from
        $this->get('/quick/vehicles?q=4455&customer_id='.$c->id)->assertOk()->assertSee('Salim Al Harthy')->assertSee('value="4455"', false);
        $v = $this->postJson('/quick/vehicles', ['customer_id' => $c->id, 'plate_number' => '4455', 'plate_code' => 'AB'])->assertOk()->json('item');
        $this->assertSame($c->id, $v['extra']['customer_id']);
        $this->postJson('/quick/vehicles', ['customer_id' => $c->id, 'plate_number' => '4455', 'plate_code' => 'AB'])->assertStatus(422); // duplicate plate

        $p = $this->postJson('/quick/parts', ['name_ar' => 'فلتر زيت', 'part_type' => 'genuine', 'sale_price' => 4.5, 'oem_number' => '90915-YZZE1'])->assertOk()->json('item');
        $part = Part::query()->findOrFail($p['id']);
        $this->assertNotEmpty($part->sku);                    // generated
        $this->assertTrue($part->is_active);
        $this->assertSame('4.500', $p['extra']['sale_price']);

        $this->postJson('/quick/services', ['code' => 'SRV-77', 'name_ar' => 'تغيير زيت', 'price' => 5])->assertOk()->assertJsonPath('item.extra.price', '5.000');
        $this->postJson('/quick/suppliers', ['name' => 'Gulf Parts LLC'])->assertOk();
        $this->assertTrue(Supplier::query()->where('name', 'Gulf Parts LLC')->value('is_active'));
        $this->get('/quick/nothing')->assertNotFound();
    }

    public function test_quick_add_respects_permissions_and_layout_flags(): void
    {
        $this->get('/dashboard')->assertOk()->assertSee('quickTypes', false)->assertSee('"suppliers"', false);
        $this->actAsRole('Cashier');                          // may create customers, not parts or suppliers
        $this->get('/quick/customers')->assertOk();
        $this->get('/quick/parts')->assertForbidden();
        $this->postJson('/quick/suppliers', ['name' => 'X'])->assertForbidden();
    }

    /* ---------------- fleets ---------------- */

    public function test_vehicles_can_be_added_to_and_removed_from_a_fleet(): void
    {
        $customer = $this->makeCustomer(['name' => 'Oman Logistics', 'type' => 'company']);
        $fleet = Fleet::create(['name' => 'OL Trucks', 'customer_id' => $customer->id, 'status' => 'active']);
        $v1 = Vehicle::create(['customer_id' => $customer->id, 'plate_number' => '1001', 'plate_country' => 'OM', 'vehicle_type' => 'truck']);
        $v2 = Vehicle::create(['customer_id' => $customer->id, 'plate_number' => '1002', 'plate_country' => 'OM', 'vehicle_type' => 'truck']);

        $this->get('/fleets/'.$fleet->id)->assertOk()->assertSee(__('Add a vehicle to this fleet'));
        $this->post('/fleets/'.$fleet->id.'/vehicles', ['vehicle_id' => $v1->id])->assertSessionHas('success');
        $this->assertSame($fleet->id, $v1->fresh()->fleet_id);
        $this->get('/fleets/'.$fleet->id)->assertSee('1001');

        // vehicle form: the fleet can be chosen there too
        $this->get('/m/vehicles/'.$v2->id.'/edit')->assertOk()->assertSee('name="fleet_id"', false)->assertSee('OL Trucks');
        $other = Fleet::create(['name' => 'Other', 'customer_id' => $customer->id, 'status' => 'active']);
        $this->post('/fleets/'.$other->id.'/vehicles', ['vehicle_id' => $v1->id])->assertSessionHas('error');   // already in a fleet
        $this->assertSame($fleet->id, $v1->fresh()->fleet_id);

        $this->delete('/fleets/'.$other->id.'/vehicles/'.$v1->id)->assertNotFound();
        $this->delete('/fleets/'.$fleet->id.'/vehicles/'.$v1->id)->assertSessionHas('success');
        $this->assertNull($v1->fresh()->fleet_id);
    }

    /* ---------------- invoices ---------------- */

    public function test_invoice_page_prints_warranties_and_lists_render(): void
    {
        $wh = $this->mainWarehouse()->id;
        $part = $this->makePart(['name_ar' => 'دينمو', 'warranty_days' => 180]);
        app(StockService::class)->receive($part, $wh, 5, 10, ['type' => 'opening']);
        $plain = $this->makePart();
        app(StockService::class)->receive($plain, $wh, 5, 10, ['type' => 'opening']);
        $svc = app(InvoiceService::class);
        $inv = $svc->post($svc->createDraft(['customer_id' => $this->makeCustomer(['name' => 'Hamad'])->id, 'warehouse_id' => $wh, 'payment_terms' => 'credit'], [
            ['item_type' => 'part', 'part_id' => $part->id, 'qty' => 1, 'unit_price' => 60, 'tax_rate' => 5],
            ['item_type' => 'part', 'part_id' => $plain->id, 'qty' => 1, 'unit_price' => 20, 'tax_rate' => 5],
        ]));
        $w = Warranty::query()->where('invoice_id', $inv->id)->firstOrFail();
        $this->assertSame(1, Warranty::query()->where('invoice_id', $inv->id)->count());

        $this->get('/invoices/'.$inv->id)->assertOk()->assertSee('Hamad')->assertSee('warranty-chip', false)
            ->assertSee(route('invoices.warranties', $inv), false)->assertSee(route('invoices.warranties', [$inv, 'a4', 'w' => $w->id]), false);
        $this->get(route('invoices.warranties', $inv))->assertOk()->assertSee('بطاقة ضمان')->assertSee('WR-'.str_pad((string) $w->id, 6, '0', STR_PAD_LEFT));
        $this->get(route('invoices.warranties', [$inv, 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // a salesperson can print the warranty from the invoice
        $this->actAsRole('Salesperson');
        $this->get(route('invoices.warranties', [$inv, 'a4', 'w' => $w->id]))->assertOk();

        $this->actingAs($this->admin);
        $this->get('/m/invoices')->assertOk()->assertSee('status-tabs', false)->assertSee($inv->number)->assertSee('Hamad');
        $this->get('/m/invoices?f_status=paid')->assertOk()->assertDontSee('>'.$inv->number.'<', false);
        $this->get('/m/invoices?f_status=posted&q='.$inv->number)->assertOk()->assertSee($inv->number);
        $this->get('/m/supplier-invoices')->assertOk()->assertSee('status-tabs', false);
    }

    /* ---------------- dismantling checklist ---------------- */

    public function test_dismantling_checklist_registers_parts_and_suggests_factory_numbers(): void
    {
        $make = VehicleMake::query()->firstOrCreate(['name' => 'Toyota']);
        $model = VehicleModel::query()->firstOrCreate(['vehicle_make_id' => $make->id, 'name' => 'Camry']);
        $supplier = Supplier::create(['code' => 'S-1', 'name' => 'Auction House']);
        $wh = $this->mainWarehouse()->id;

        $this->get('/m/dismantled-vehicles/create')->assertOk()->assertSee(__('Parts to extract'))->assertSee('checklist-inputs', false);
        $this->post('/m/dismantled-vehicles', [
            'vin' => 'JTMHV05J804012345', 'vehicle_make_id' => $make->id, 'vehicle_model_id' => $model->id, 'year' => 2018,
            'warehouse_id' => $wh, 'source' => 'auction', 'supplier_id' => $supplier->id, 'purchase_date' => today()->toDateString(), 'purchase_cost' => 1000,
            'checklist' => [
                'engine' => ['selected' => 1, 'oem' => '19000-0V050', 'grade' => 'A', 'estimated_value' => 700, 'sale_price' => 950],
                'headlight_l' => ['selected' => 1, 'oem' => '81150-06C40', 'grade' => 'B', 'estimated_value' => 300],
                'gearbox' => ['oem' => 'ignored-not-selected'],
            ],
            'checklist_to_stock' => 1, '_idem' => 'dv-1',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $v = DismantledVehicle::query()->firstOrFail();
        $parts = DismantledPart::query()->where('dismantled_vehicle_id', $v->id)->orderBy('id')->get();
        $this->assertSame(['engine', 'headlight_l'], $parts->pluck('template_key')->all());
        $this->assertSame(['in_stock', 'in_stock'], $parts->pluck('status')->all());       // moved to the warehouse right away
        $this->assertSame('19000-0V050', $parts[0]->part->oem_number);
        $this->assertMoney(1000, $v->fresh()->allocated_cost);

        // the next Camry gets the factory numbers suggested
        $s = $this->getJson('/dismantling/oem-suggest?make='.$make->id.'&model='.$model->id.'&year=2019')->assertOk()->json('suggestions');
        $this->assertSame('19000-0V050', $s['engine']['oem']);
        $this->assertSame('history', $s['engine']['source']);
        $this->assertArrayNotHasKey('gearbox', $s);

        // a catalog part listed as compatible is suggested too
        $cat = $this->makePart(['name_ar' => 'جير (ناقل الحركة)', 'name_en' => 'Gearbox', 'oem_number' => '30500-33480']);
        $cat->compatibilities()->create(['vehicle_make_id' => $make->id, 'vehicle_model_id' => $model->id, 'year_from' => 2015, 'year_to' => 2020]);
        $s = $this->getJson('/dismantling/oem-suggest?make='.$make->id.'&model='.$model->id.'&year=2019')->json('suggestions');
        $this->assertSame('30500-33480', $s['gearbox']['oem']);
        $this->assertSame('catalog', $s['gearbox']['source']);

        // second vehicle: checklist on the dismantling file; the same OEM + grade joins the existing used part
        $v2 = app(DismantlingService::class)->purchase(['vehicle_make_id' => $make->id, 'vehicle_model_id' => $model->id, 'year' => 2019, 'source' => 'auction',
            'supplier_id' => $supplier->id, 'purchase_date' => today()->toDateString(), 'purchase_cost' => 500, 'warehouse_id' => $wh]);
        $this->get('/dismantling/'.$v2->id)->assertOk()->assertSee(__('Vehicle parts checklist'));
        $this->post('/dismantling/'.$v2->id.'/checklist', ['checklist' => []])->assertSessionHas('error');
        $this->post('/dismantling/'.$v2->id.'/checklist', ['warehouse_id' => $wh, 'checklist_to_stock' => 1, 'checklist' => [
            'engine' => ['selected' => 1, 'oem' => '19000-0V050', 'grade' => 'A', 'estimated_value' => 500],
        ]])->assertSessionHas('success');
        $p2 = DismantledPart::query()->where('dismantled_vehicle_id', $v2->id)->firstOrFail();
        $this->assertSame('in_stock', $p2->status);
        $this->assertSame($parts[0]->part_id, $p2->part_id);
        $this->assertLedgerBalanced();
    }
}
