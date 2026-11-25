<?php

namespace Tests\Feature;

use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Workshop\Models\JobCard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end web form submissions (controllers + requests + services + views).
 */
class WebFlowsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
        tenant()->forget(); // let the middleware resolve the tenant like a real request
    }

    protected function inTenant(callable $fn): mixed
    {
        return tenant()->run($this->company->id, $fn);
    }

    public function test_reception_check_in_creates_customer_vehicle_and_job_card(): void
    {
        Storage::fake('local');
        // 1x1 transparent PNG as the signature pad output
        $sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        $res = $this->post('/reception', [
            'customer_name' => 'Walk In Ahmed', 'customer_phone' => '92223344', 'plate_number' => '12345', 'plate_code' => 'ab',
            // the fuel slider posts eighths of a tank (37.5 %)
            'odometer' => 80500, 'fuel_level' => '37.5', 'reported_issues' => 'Engine light on',
            'damages' => [['area' => 'front', 'type' => 'scratch', 'x' => 10, 'y' => 20]],
            'personal_items' => ['Sunglasses'], 'signature' => $sig, 'customer_signed_name' => 'Ahmed',
            'photos' => [UploadedFile::fake()->image('front.jpg', 400, 300)],
            '_idem' => 'checkin-1',
        ]);
        $res->assertRedirect()->assertSessionHasNoErrors();

        $jc = $this->inTenant(fn () => JobCard::query()->with('customer', 'vehicle')->latest('id')->first());
        $this->assertSame('+96892223344', $jc->customer->phone);
        $this->assertSame('AB', $jc->vehicle->plate_code);
        $this->assertSame(80500, (int) $jc->odometer_in);
        $this->assertSame(38, (int) $jc->fuel_level);

        $this->get('/job-cards/'.$jc->id)->assertOk()->assertSee($jc->number);
        $this->get('/reception/'.$jc->id.'/receipt')->assertOk();
        $this->get('/job-cards/'.$jc->id.'/print')->assertOk();

        // add a task, a note and a part from the job card screen
        $part = $this->inTenant(function () {
            $p = $this->makePart();
            app(StockService::class)->receive($p, $this->mainWarehouse()->id, 4, 3, ['type' => 'opening']);

            return $p;
        });
        $this->post('/job-cards/'.$jc->id.'/tasks', ['description' => 'Scan & diagnose', 'qty' => 1, 'unit_price' => 10])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/job-cards/'.$jc->id.'/parts', ['part_id' => $part->id, 'qty' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/job-cards/'.$jc->id.'/notes', ['note' => 'Customer waiting'])->assertRedirect()->assertSessionHasNoErrors();
        $this->inTenant(function () use ($jc) {
            $this->assertSame(1, $jc->tasks()->count());
            $this->assertSame(1, $jc->parts()->count());
        });
    }

    public function test_sales_invoice_form_posts_and_receives_payment(): void
    {
        [$part, $customer, $wh] = $this->inTenant(function () {
            $p = $this->makePart();
            app(StockService::class)->receive($p, $this->mainWarehouse()->id, 10, 6, ['type' => 'opening']);

            return [$p, $this->makeCustomer(), $this->mainWarehouse()->id];
        });

        $this->post('/invoices', [
            'customer_id' => $customer->id, 'issue_date' => now()->toDateString(), 'payment_terms' => 'credit', 'price_level' => 'retail',
            'warehouse_id' => $wh, 'post_now' => 1, '_idem' => 'inv-1',
            'items' => [['item_type' => 'part', 'part_id' => $part->id, 'qty' => 3, 'unit_price' => 20, 'tax_rate' => 5]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $inv = $this->inTenant(fn () => Invoice::query()->latest('id')->first());
        $this->assertSame('posted', $inv->status);
        $this->assertMoney('63.000', $inv->total);

        $this->get('/invoices/'.$inv->id)->assertOk()->assertSee($inv->number);
        foreach (['a4', '80mm', 'pdf'] as $format) {
            $this->get('/invoices/'.$inv->id.'/print/'.$format)->assertOk();
        }

        $this->post('/payments/receive', [
            'customer_id' => $customer->id, 'amount' => '63.000', 'bank_account_id' => $this->inTenant(fn () => $this->cashAccountId()),
            'method' => 'cash', 'date' => now()->toDateString(), 'allocations' => [$inv->id => '63.000'], '_idem' => 'pay-1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('paid', $this->inTenant(fn () => $inv->fresh()->status));
        $this->inTenant(fn () => $this->assertLedgerBalanced());
    }

    public function test_purchase_order_and_stock_adjustment_forms(): void
    {
        [$part, $supplier, $wh] = $this->inTenant(fn () => [$this->makePart(), Supplier::create(['code' => 'S-9', 'name' => 'Supplier']), $this->mainWarehouse()->id]);

        $this->post('/purchase-orders', [
            'supplier_id' => $supplier->id, 'warehouse_id' => $wh, '_idem' => 'po-1',
            'items' => [['part_id' => $part->id, 'qty' => 5, 'unit_cost' => 7, 'tax_rate' => 5]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $po = $this->inTenant(fn () => PurchaseOrder::query()->latest('id')->first());
        $this->assertMoney('36.750', $po->total);
        $this->get('/purchase-orders/'.$po->id)->assertOk();
        $this->get('/purchase-orders/'.$po->id.'/print')->assertOk();

        $this->post('/inventory/adjustments', [
            'warehouse_id' => $wh, 'date' => now()->toDateString(), 'reason' => 'opening', 'post_now' => 1, '_idem' => 'adj-1',
            'items' => [['part_id' => $part->id, 'qty' => 12, 'unit_cost' => 7]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->inTenant(fn () => $this->assertMoney(12, StockLevel::query()->where('part_id', $part->id)->value('qty_on_hand')));
    }

    public function test_generic_crud_create_edit_and_export(): void
    {
        $this->post('/m/parts', ['sku' => 'NEW-1', 'name_ar' => 'فلتر هواء', 'name_en' => 'Air filter', 'part_type' => 'aftermarket',
            'purchase_price' => 2, 'sale_price' => 4, 'tax_rate' => 5, '_idem' => 'crud-1'])->assertRedirect()->assertSessionHasNoErrors();
        $id = $this->inTenant(fn () => \App\Modules\Inventory\Models\Part::query()->where('sku', 'NEW-1')->value('id'));
        $this->assertNotNull($id);

        $this->get('/m/parts/'.$id.'/edit')->assertOk();
        $this->put('/m/parts/'.$id, ['sku' => 'NEW-1', 'name_ar' => 'فلتر هواء', 'name_en' => 'Air filter (OEM)', 'part_type' => 'oem',
            'purchase_price' => 2, 'sale_price' => 5, 'tax_rate' => 5])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/m/parts?q=NEW-1')->assertOk()->assertSee('Air filter (OEM)');

        foreach (['csv', 'xlsx', 'pdf', 'print'] as $format) {
            $this->get('/m/parts?export='.$format)->assertOk();
        }
    }
}
