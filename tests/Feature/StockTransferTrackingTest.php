<?php

namespace Tests\Feature;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use Tests\TestCase;

class StockTransferTrackingTest extends TestCase
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

    public function test_transfer_tracks_requester_dispatcher_receiver_and_prints(): void
    {
        [$from, $to, $part] = $this->inTenant(function () {
            $from = $this->mainWarehouse();
            $to = Warehouse::create(['code' => 'WH2', 'name' => 'Sohar branch store', 'is_active' => true]);
            $part = $this->makePart(['sku' => 'TR-PART-1']);
            app(StockService::class)->receive($part, $from->id, 10, 5, ['type' => 'opening']);

            return [$from, $to, $part];
        });

        $this->post('/inventory/transfers', ['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id, 'transfer_date' => now()->toDateString(),
            'notes' => 'For Sohar workshop', 'items' => [['part_id' => $part->id, 'qty' => 4]], '_idem' => 'tr-1'])->assertRedirect();
        $t = $this->inTenant(fn () => StockTransfer::query()->firstOrFail());
        $me = auth()->user();
        $this->assertSame($me->id, $t->created_by);

        // request printout
        $this->get('/inventory/transfers/'.$t->id.'/print/request')->assertOk()->assertSee('طلب نقل مخزون')->assertSee($t->number)
            ->assertSee('TR-PART-1')->assertSee('Sohar branch store')->assertSee($me->name);

        $this->post('/inventory/transfers/'.$t->id.'/send', ['_idem' => 'tr-2'])->assertSessionHas('success');
        $this->post('/inventory/transfers/'.$t->id.'/note', ['note' => 'Loaded on truck 12'])->assertSessionHas('success');
        $this->post('/inventory/transfers/'.$t->id.'/receive', ['received' => [$t->items()->first()->id => 3], 'notes' => '1 box damaged', '_idem' => 'tr-3'])->assertSessionHas('success');

        $t->refresh();
        $this->assertSame('received', $t->status);
        $this->assertSame($me->id, $t->sent_by);
        $this->assertSame($me->id, $t->received_by);
        $events = $this->inTenant(fn () => $t->events()->pluck('event')->sort()->values()->all());
        $this->assertSame(['dispatched', 'note', 'received', 'requested'], $events);

        $this->get('/inventory/transfers/'.$t->id)->assertOk()->assertSee('Loaded on truck 12')->assertSee('1 box damaged')
            ->assertSee(route('inventory.transfers.print', [$t, 'completed']), false);
        $this->get('/inventory/transfers/'.$t->id.'/print/completed')->assertOk()->assertSee('سند نقل مخزون مكتمل')->assertSee(qty(-1))->assertSee('1 box damaged', false);
        $this->get('/inventory/transfers/'.$t->id.'/print/completed/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/m/stock-transfers')->assertOk()->assertSee($me->name)->assertSee(route('inventory.transfers.print', [$t, 'completed']), false);
    }

    public function test_cancellation_records_who_and_why(): void
    {
        [$from, $to, $part] = $this->inTenant(function () {
            $to = Warehouse::create(['code' => 'WH3', 'name' => 'Nizwa store', 'is_active' => true]);

            return [$this->mainWarehouse(), $to, $this->makePart()];
        });
        $this->post('/inventory/transfers', ['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id, 'transfer_date' => now()->toDateString(),
            'items' => [['part_id' => $part->id, 'qty' => 1]], '_idem' => 'tc-1']);
        $t = $this->inTenant(fn () => StockTransfer::query()->firstOrFail());
        $this->post('/inventory/transfers/'.$t->id.'/cancel', ['reason' => 'Not needed anymore', '_idem' => 'tc-2'])->assertSessionHas('success');
        $t->refresh();
        $this->assertSame('cancelled', $t->status);
        $this->assertSame(auth()->id(), $t->cancelled_by);
        $this->get('/inventory/transfers/'.$t->id)->assertOk()->assertSee('Not needed anymore');
    }
}
