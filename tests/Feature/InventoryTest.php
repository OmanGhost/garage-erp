<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryDocuments;
use App\Modules\Inventory\Services\StockService;
use App\Support\DocumentNumber;
use App\Support\Settings;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    public function test_weighted_average_cost_on_receipts_and_issue(): void
    {
        $stock = app(StockService::class);
        $wh = $this->mainWarehouse()->id;
        $part = $this->makePart();

        $stock->receive($part, $wh, 10, '10.000', ['type' => 'opening']);
        $stock->receive($part, $wh, 10, '20.000', ['type' => 'purchase_receipt']);

        $level = StockLevel::query()->where('part_id', $part->id)->first();
        $this->assertMoney(20, $level->qty_on_hand);
        $this->assertMoney(15, $level->avg_cost);

        $out = $stock->issue($part, $wh, 4, ['type' => 'sale']);
        $this->assertMoney(60, $out['total_cost']);
        $this->assertMoney(16, $level->fresh()->qty_on_hand);
    }

    public function test_fifo_consumes_oldest_layers_first(): void
    {
        Settings::set(['inventory.valuation_method' => 'fifo']);
        $stock = app(StockService::class);
        $wh = $this->mainWarehouse()->id;
        $part = $this->makePart();

        $stock->receive($part, $wh, 5, '10.000', ['type' => 'opening']);
        $stock->receive($part, $wh, 5, '20.000', ['type' => 'purchase_receipt']);

        // 5 @ 10 + 2 @ 20 = 90
        $out = $stock->issue($part, $wh, 7, ['type' => 'sale']);
        $this->assertMoney(90, $out['total_cost']);
        // remaining 3 @ 20
        $this->assertMoney(20, StockLevel::query()->where('part_id', $part->id)->value('avg_cost'));
    }

    public function test_negative_stock_is_prevented(): void
    {
        $stock = app(StockService::class);
        $part = $this->makePart();
        $stock->receive($part, $this->mainWarehouse()->id, 2, 5, ['type' => 'opening']);

        $this->expectException(BusinessException::class);
        $stock->issue($part, $this->mainWarehouse()->id, 3, ['type' => 'sale']);
    }

    public function test_reserved_stock_is_not_available_to_others(): void
    {
        $stock = app(StockService::class);
        $wh = $this->mainWarehouse()->id;
        $part = $this->makePart();
        $stock->receive($part, $wh, 5, 5, ['type' => 'opening']);
        $stock->reserve($part->id, $wh, 4);

        $this->expectException(BusinessException::class);
        $stock->issue($part, $wh, 2, ['type' => 'sale']);
    }

    public function test_serial_numbers_must_match_quantity(): void
    {
        $part = $this->makePart(['track_serial' => true]);

        $this->expectException(BusinessException::class);
        app(StockService::class)->receive($part, $this->mainWarehouse()->id, 2, 5, ['type' => 'opening', 'serials' => ['SN-1']]);
    }

    public function test_transfer_between_warehouses_keeps_total_quantity_and_value(): void
    {
        $stock = app(StockService::class);
        $from = $this->mainWarehouse();
        $to = Warehouse::create(['branch_id' => $from->branch_id, 'code' => 'WH-2', 'name' => 'Second', 'type' => 'main']);
        $part = $this->makePart();
        $stock->receive($part, $from->id, 10, 8, ['type' => 'opening']);

        $docs = app(InventoryDocuments::class);
        $t = $docs->createTransfer(['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id, 'transfer_date' => now()->toDateString()],
            [['part_id' => $part->id, 'qty' => 4]]);
        $docs->sendTransfer($t);
        $this->assertMoney(6, StockLevel::query()->where('warehouse_id', $from->id)->where('part_id', $part->id)->value('qty_on_hand'));
        $docs->receiveTransfer($t->fresh(), [$t->items()->first()->id => 4]);

        $this->assertMoney(6, StockLevel::query()->where('warehouse_id', $from->id)->where('part_id', $part->id)->value('qty_on_hand'));
        $this->assertMoney(4, StockLevel::query()->where('warehouse_id', $to->id)->where('part_id', $part->id)->value('qty_on_hand'));
        $this->assertMoney(80, $stock->valuation());
        $this->assertLedgerBalanced();
    }
}
