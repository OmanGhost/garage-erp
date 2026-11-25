<?php

namespace Tests\Feature;

use App\Livewire\KanbanBoard;
use App\Livewire\PosTerminal;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Models\CashRegister;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Services\PosService;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Services\JobCardService;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    public function test_pos_terminal_scan_hold_resume_and_checkout(): void
    {
        $part = $this->makePart(['barcode' => '6281000000017', 'sale_price' => 5]);
        $register = CashRegister::query()->firstOrFail();
        app(StockService::class)->receive($part, $register->warehouse_id, 10, 2, ['type' => 'opening']);
        $session = app(PosService::class)->openSession($register, 10);

        $c = Livewire::test(PosTerminal::class, ['session' => $session])
            ->call('scanned', '6281000000017')
            ->call('scanned', '6281000000017')
            ->assertSet('cart.0.qty', '2');

        // 2 x 5 + 5% = 10.500
        $this->assertSame('10.500', $c->instance()->totals()['total']);

        $c->call('hold')->assertSet('cart', []);
        $held = Invoice::query()->where('status', 'held')->firstOrFail();

        $c->call('resume', $held->id)->assertCount('cart', 1)
            ->call('payExact')
            ->call('checkout')
            ->assertSet('cart', []);

        $this->assertSame('paid', $held->fresh()->status);
        $this->assertSame(0, Invoice::query()->where('status', 'held')->count());
        $this->assertLedgerBalanced();
    }

    public function test_kanban_move_respects_the_workflow(): void
    {
        $customer = $this->makeCustomer();
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'plate_number' => '7788']);
        $jc = app(JobCardService::class)->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'customer_complaint' => 'Check']);

        $this->actAsRole('Technician');
        Livewire::test(KanbanBoard::class)
            ->call('move', $jc->id, JobCard::UNDER_INSPECTION)
            ->assertDispatched('erp-toast');
        $this->assertSame(JobCard::UNDER_INSPECTION, $jc->fresh()->status);

        // jumping to delivered is refused and reported, not thrown
        Livewire::test(KanbanBoard::class)
            ->call('move', $jc->id, JobCard::DELIVERED)
            ->assertDispatched('erp-toast', type: 'danger');
        $this->assertSame(JobCard::UNDER_INSPECTION, $jc->fresh()->status);
    }
}
