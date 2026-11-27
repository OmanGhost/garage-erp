<?php

namespace Tests\Feature;

use App\Modules\Inventory\Models\ConsignmentSettlement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Services\ReservationService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Sales\Models\CoreReturn;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\InvoiceService;
use Tests\TestCase;

class InventoryStockOpsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    public function test_reservations_and_kit_movements_respect_available_stock(): void
    {
        $wh = $this->mainWarehouse()->id;
        $part = $this->makePart();
        $kit = $this->makePart();
        app(StockService::class)->receive($part, $wh, 6, 4);
        $this->post(route('inventory.reservations.store'), ['part_id' => $part->id, 'warehouse_id' => $wh, 'qty' => 3])->assertSessionHas('success');
        $reservation = StockReservation::firstOrFail();
        $this->get(route('inventory.reservations'))->assertOk()->assertSee($reservation->number);
        $this->post(route('inventory.kit.components', $kit), ['components' => [['component_part_id' => $part->id, 'qty' => 2]]])->assertSessionHas('success');
        $this->get(route('parts.show', $kit))->assertOk()->assertSee($part->sku);
        $build = ['warehouse_id' => $wh, 'qty' => 2];
        $this->post(route('inventory.kit.build', [$kit, 'assemble']), $build)->assertSessionHas('error');
        $reservation->update(['expires_at' => today()->subDay()]);
        $this->assertSame(1, app(ReservationService::class)->expire());
        $this->assertSame('expired', $reservation->fresh()->status);
        $this->post(route('inventory.kit.build', [$kit, 'assemble']), $build)->assertSessionHas('success');
        $this->assertMoney(2, $kit->available($wh));
        $this->assertMoney(8, $kit->fresh()->average_cost);
        $this->post(route('inventory.kit.build', [$kit, 'disassemble']), $build)->assertSessionHas('success');
        $this->assertMoney(6, $part->available($wh));
        $this->assertMoney(0, $kit->available($wh));
    }

    public function test_core_deposits_are_refunded_or_forfeited_once(): void
    {
        $wh = $this->mainWarehouse()->id;
        $part = $this->makePart(['core_charge' => 5]);
        app(StockService::class)->receive($part, $wh, 3, 10);
        $svc = app(InvoiceService::class);
        $line = ['part_id' => $part->id, 'unit_price' => 20, 'qty' => 1, 'tax_rate' => 0];
        $invoice = $svc->post($svc->createDraft(['customer_id' => $this->makeCustomer()->id, 'warehouse_id' => $wh], [$line, $line]));
        $this->assertMoney(50, $invoice->total);
        $this->assertMoney(-10, $this->balance('customer_advances'));
        [$first, $second] = CoreReturn::orderBy('id')->get()->all();
        $this->get(route('inventory.cores'))->assertOk()->assertSee($invoice->number);
        $this->post(route('inventory.cores.action', [$first, 'receive']))->assertSessionHas('success');
        $this->assertMoney(5, $first->fresh()->creditNote->total);
        $this->post(route('inventory.cores.action', [$first, 'forfeit']))->assertSessionHas('error');
        $this->post(route('inventory.cores.action', [$second, 'forfeit']))->assertSessionHas('success');
        $this->assertMoney(0, $this->balance('customer_advances'));
        $this->assertMoney(-5, $this->balance('other_revenue'));
        $this->assertMoney(1, $part->available($wh));
        $this->assertLedgerBalanced();
    }

    public function test_consignment_bills_each_sale_once_including_same_second_sales(): void
    {
        $this->freezeSecond();
        $wh = $this->mainWarehouse();
        $supplier = Supplier::create(['code' => 'CS', 'name' => 'Consignor']);
        $wh->update(['is_consignment' => true, 'consignor_supplier_id' => $supplier->id]);
        $part = $this->makePart();
        $url = fn ($action) => route('inventory.consignment.action', [$wh, $action]);
        $this->post($url('receive'), ['lines' => [['part_id' => $part->id, 'qty' => 6, 'unit_cost' => 10]]])->assertSessionHas('success');
        $this->get(route('inventory.consignment'))->assertOk()->assertSee('Consignor');
        $svc = app(InvoiceService::class);
        $customer = $this->makeCustomer();
        foreach ([2, 1] as $qty) {
            $svc->post($svc->createDraft(['customer_id' => $customer->id, 'warehouse_id' => $wh->id], [['part_id' => $part->id, 'qty' => $qty]]));
            $this->get(route('inventory.consignment.show', $wh))->assertOk()->assertSee($part->sku);
            $this->post($url('settle'), ['supplier_invoice_number' => 'B-'.$qty])->assertSessionHas('success');
            $invoice = ConsignmentSettlement::latest('id')->firstOrFail()->supplierInvoice;
            $this->assertMoney($qty * 10, $invoice->total);
            app(PurchasingService::class)->postSupplierInvoice($invoice);
        }
        $this->post($url('settle'))->assertSessionHas('error');
        $this->post($url('return'), ['lines' => [['part_id' => $part->id, 'qty' => 3]]])->assertSessionHas('success');
        $this->assertMoney(0, $part->available($wh->id));
        $this->assertMoney(0, $this->balance('grni'));
        $this->assertLedgerBalanced();
    }

    public function test_drop_shipping_uses_approved_po_and_delivers_only_once(): void
    {
        $supplier = Supplier::create(['code' => 'DS', 'name' => 'Direct supplier']);
        $customer = $this->makeCustomer(['address' => 'Muscat delivery address']);
        $part = $this->makePart();
        $so = SalesOrder::create(['number' => 'SO-D', 'customer_id' => $customer->id, 'order_date' => today(), 'status' => 'draft', 'warehouse_id' => $this->mainWarehouse()->id]);
        $so->items()->create(['part_id' => $part->id, 'description' => $part->label(), 'qty' => 2, 'unit_price' => 20, 'line_total' => 40]);
        $url = route('sales-orders.drop-ship', $so);
        $this->get(route('sales-orders.show', $so))->assertOk();
        $this->post($url, ['supplier_id' => $supplier->id])->assertSessionHas('success');
        $po = $so->dropShipOrder()->firstOrFail();
        $this->get(route('purchase-orders.show', $po))->assertOk()->assertSee($customer->address);
        $this->post($url, ['supplier_id' => $supplier->id])->assertSessionHas('error');
        $this->post(route('sales-orders.action', [$so, 'invoice']))->assertSessionHas('error');
        $delivery = route('purchase-orders.delivered', $po);
        $this->post($delivery)->assertSessionHas('error');
        $this->post(route('purchase-orders.action', [$po, 'approve']))->assertSessionHas('success');
        $this->post($delivery)->assertSessionHas('success');
        $this->post($delivery)->assertSessionHas('error');
        $this->assertSame(1, $po->receipts()->count());
        $invoice = $so->fresh()->invoice;
        $this->assertEquals($po->warehouse_id, $invoice->warehouse_id);
        $this->assertMoney(2, $part->available($po->warehouse_id));
        app(InvoiceService::class)->post($invoice);
        $this->assertMoney(0, $part->available());
        $this->assertLedgerBalanced();
    }
}
