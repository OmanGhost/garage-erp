<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Modules\Dismantling\Services\DismantlingService;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Purchasing\Models\LandedCost;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Support\DocumentNumber;
use Tests\TestCase;

class PurchasingDismantlingTest extends TestCase
{
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
        $this->supplier = Supplier::create(['code' => 'S-1', 'name' => 'Gulf Parts', 'payment_terms_days' => 30]);
    }

    protected function approvedOrder(array $lines): PurchaseOrder
    {
        $svc = app(PurchasingService::class);
        $po = $svc->createOrder(['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->mainWarehouse()->id], $lines);
        $svc->approveOrder($po);

        return $po->fresh('items');
    }

    public function test_po_receipt_and_supplier_invoice_clear_grni(): void
    {
        $part = $this->makePart();
        $svc = app(PurchasingService::class);
        $po = $this->approvedOrder([['part_id' => $part->id, 'qty' => 10, 'unit_cost' => 4, 'tax_rate' => 5]]);
        $this->assertMoney('42.000', $po->total);

        // partial receipt
        $item = $po->items->first();
        $grn = $svc->receive($po, [$item->id => ['qty' => 6]]);
        $this->assertSame('partially_received', $po->fresh()->status);
        $this->assertMoney(6, StockLevel::query()->where('part_id', $part->id)->value('qty_on_hand'));
        $this->assertMoney(24, $this->balance('grni') * -1);

        // over-receipt is refused
        try {
            $svc->receive($po->fresh('items'), [$item->id => ['qty' => 5]]);
            $this->fail('Over-receipt accepted');
        } catch (BusinessException) {
        }

        $svc->postSupplierInvoice($svc->invoiceFromReceipt($grn, 'INV-778'));
        $this->assertMoney(0, $this->balance('grni'));
        $this->assertMoney('-25.200', $this->balance('ap'));
        $this->assertMoney('1.200', $this->balance('vat_input'));

        // the same receipt cannot be invoiced twice
        $this->expectException(BusinessException::class);
        $svc->invoiceFromReceipt($grn, 'INV-779');
    }

    public function test_landed_cost_is_capitalised_into_stock(): void
    {
        $a = $this->makePart();
        $b = $this->makePart();
        $svc = app(PurchasingService::class);
        $po = $this->approvedOrder([
            ['part_id' => $a->id, 'qty' => 10, 'unit_cost' => 10, 'tax_rate' => 0],
            ['part_id' => $b->id, 'qty' => 10, 'unit_cost' => 30, 'tax_rate' => 0],
        ]);
        $grn = $svc->receive($po, $po->items->mapWithKeys(fn ($i) => [$i->id => ['qty' => 10]])->all());

        $lc = LandedCost::create(['number' => DocumentNumber::next('landed_cost'), 'goods_receipt_id' => $grn->id, 'date' => now()->toDateString(),
            'allocation_method' => 'value', 'status' => 'draft']);
        $lc->lines()->create(['type' => 'freight', 'amount' => 40]);
        $svc->postLandedCost($lc);

        // value basis: 100 / 300 => 10 and 30 => +1.000 and +3.000 per unit
        $this->assertMoney(11, StockLevel::query()->where('part_id', $a->id)->value('avg_cost'));
        $this->assertMoney(33, StockLevel::query()->where('part_id', $b->id)->value('avg_cost'));
        $this->assertMoney(440, $this->balance('inventory'));
        $this->assertLedgerBalanced();
    }

    public function test_dismantled_vehicle_cost_is_allocated_to_used_parts(): void
    {
        $dis = app(DismantlingService::class);
        $v = $dis->purchase(['vin' => 'JTMHV05J804012345', 'source' => 'auction', 'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(), 'purchase_cost' => 1000, 'warehouse_id' => $this->mainWarehouse()->id]);
        $dis->addCost($v, 'transport_cost', 200);
        $this->assertMoney(1200, $this->balance('dismantling'));

        $dis->addPart($v, ['name' => 'Engine', 'grade' => 'A', 'estimated_value' => 900]);
        $dis->addPart($v, ['name' => 'Gearbox', 'grade' => 'B', 'estimated_value' => 300]);
        $dis->moveToStock($v->fresh());

        // cost 1200 split by estimated value 900:300 => 900 / 300
        $parts = $v->parts()->orderBy('id')->get();
        $this->assertSame(['in_stock', 'in_stock'], $parts->pluck('status')->all());
        $this->assertMoney(0, $this->balance('dismantling'));
        $this->assertMoney(1200, $this->balance('inventory'));
        $this->assertMoney(1200, $v->fresh()->allocated_cost);
        $this->assertLedgerBalanced();
    }
}
