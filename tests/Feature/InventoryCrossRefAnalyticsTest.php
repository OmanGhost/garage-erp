<?php

namespace Tests\Feature;

use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Services\InventoryAnalyticsService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Sales\Services\InvoiceService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryCrossRefAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    public function test_cross_reference_links_genuine_aftermarket_and_used_parts(): void
    {
        $wh = $this->mainWarehouse()->id;
        $genuine = $this->makePart(['name_en' => 'Oil filter Toyota', 'oem_number' => '90915-YZZE1', 'part_type' => 'genuine']);
        $denso = $this->makePart(['name_en' => 'Denso filter', 'oem_number' => '90915 YZZE1', 'part_type' => 'aftermarket']);   // same number, other spacing
        $mann = $this->makePart(['name_en' => 'MANN W68/3', 'oem_number' => 'W68/3', 'part_type' => 'aftermarket']);
        $used = $this->makePart(['name_en' => 'Used filter', 'part_type' => 'used']);
        $other = $this->makePart(['name_en' => 'Air filter', 'oem_number' => '17801-0H050']);
        app(StockService::class)->receive($mann, $wh, 4, 2, ['type' => 'opening']);

        // MANN is linked as an alternative number of the genuine part, the used one by catalog link
        $this->post('/parts/'.$genuine->id.'/alternatives', ['number' => 'W 68/3', 'brand' => 'MANN', 'quality' => 'aftermarket'])->assertSessionHas('success');
        $this->post('/parts/'.$used->id.'/alternatives', ['alternative_part_id' => $genuine->id, 'quality' => 'used'])->assertSessionHas('success');
        $this->post('/parts/'.$genuine->id.'/alternatives', ['alternative_part_id' => $genuine->id])->assertSessionHas('error');

        $ids = app(\App\Modules\Inventory\Services\CrossReferenceService::class)->group($genuine)->pluck('id')->all();
        sort($ids);
        $expected = [$genuine->id, $denso->id, $mann->id, $used->id];
        sort($expected);
        $this->assertSame($expected, $ids);

        // search by any number of the group, from any member
        $this->get('/inventory/cross-reference?q=w683')->assertOk()->assertSee($denso->sku)->assertSee($used->sku)->assertSee($genuine->sku)->assertDontSee('>'.$other->sku.'<', false);
        $this->get('/parts/'.$denso->id)->assertOk()->assertSee($mann->sku)->assertSee($genuine->sku);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_price_history_abc_aging_forecast_auto_reorder_and_cycle_count(): void
    {
        $wh = $this->mainWarehouse()->id;
        $supplier = Supplier::create(['code' => 'S-1', 'name' => 'Gulf Parts']);
        $fast = $this->makePart(['name_en' => 'Brake pad', 'default_supplier_id' => $supplier->id, 'min_stock' => 5, 'reorder_qty' => 10]);
        $slow = $this->makePart(['name_en' => 'Mirror']);

        // two purchases: price history with change
        $p = app(PurchasingService::class);
        foreach ([10, 12] as $cost) {
            $po = $p->createOrder(['supplier_id' => $supplier->id, 'warehouse_id' => $wh], [['part_id' => $fast->id, 'qty' => 10, 'unit_cost' => $cost]]);
            $p->approveOrder($po);
            $grn = $p->receive($po->fresh(), [$po->items()->first()->id => ['qty' => 10]]);
            $p->postReceipt($grn);
        }
        app(StockService::class)->receive($slow, $wh, 3, 50, ['type' => 'opening']);
        DB::table('stock_batches')->where('part_id', $slow->id)->update(['received_at' => now()->subDays(400)]);

        $hist = app(InventoryAnalyticsService::class)->priceHistory($fast->id);
        $this->assertCount(2, $hist);
        $this->assertEquals(20.0, $hist->first()->change);          // 10 -> 12 = +20 %

        // sell most of the brake pads: they become class A, the mirror C
        $svc = app(InvoiceService::class);
        $svc->post($svc->createDraft(['customer_id' => $this->makeCustomer()->id, 'warehouse_id' => $wh], [['item_type' => 'part', 'part_id' => $fast->id, 'qty' => 18, 'unit_price' => 30, 'tax_rate' => 5]]));
        $abc = app(InventoryAnalyticsService::class)->abc();
        $this->assertSame('A', $fast->fresh()->abc_class);
        $this->assertSame('C', $slow->fresh()->abc_class);
        $this->assertSame(1, $abc['summary']['A']['parts']);

        $aging = app(InventoryAnalyticsService::class)->aging();
        $this->assertEquals(150.0, $aging['buckets']['365_plus']['value']);

        foreach (['abc', 'aging', 'dead', 'forecast', 'cycle'] as $tab) {
            $this->get('/inventory/analytics?tab='.$tab)->assertOk();
        }

        // 2 left < min 5 -> automatic reorder creates one draft PO; running again does not duplicate it
        $this->post('/inventory/analytics/reorder', ['warehouse_id' => $wh])->assertSessionHas('success');
        $this->post('/inventory/analytics/reorder', ['warehouse_id' => $wh])->assertSessionHas('info');
        $this->assertSame(1, PurchaseOrder::query()->where('status', 'draft')->count());
        $this->post('/inventory/analytics/settings', ['warehouse_id' => $wh, 'auto_reorder' => 1])->assertSessionHas('success');
        $this->assertTrue((bool) setting('inventory.auto_reorder'));

        // cycle count: A and C parts never counted are due; after posting they are not due anymore
        $this->post('/inventory/analytics/cycle', ['warehouse_id' => $wh, 'limit' => 10])->assertRedirect();
        $count = StockCount::query()->where('type', 'cycle')->firstOrFail();
        $this->assertSame(2, $count->items()->count());
        $this->assertSame($fast->id, $count->items()->orderBy('id')->first()->part_id);   // class A first
        foreach ($count->items as $item) {
            $item->update(['counted_qty' => $item->system_qty]);
        }
        app(\App\Modules\Inventory\Services\InventoryDocuments::class)->postCount($count);
        $this->assertNotNull(StockLevel::query()->where('part_id', $fast->id)->value('last_counted_at'));
        $this->assertCount(0, app(InventoryAnalyticsService::class)->dueForCount($wh));
    }
}
