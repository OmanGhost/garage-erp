<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Sales\Services\PaymentService;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Vehicles\Models\VehicleMake;
use App\Modules\Workshop\Models\Department;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\Service;
use App\Modules\Workshop\Services\JobCardService;
use App\Modules\Workshop\Services\QuotationService;
use App\Support\Settings;
use Tests\TestCase;

class WorkshopTest extends TestCase
{
    protected JobCardService $jobs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
        $this->jobs = app(JobCardService::class);
    }

    protected function newJob(): JobCard
    {
        $customer = $this->makeCustomer();
        $make = VehicleMake::query()->where('name', 'Toyota')->firstOrFail();
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'plate_number' => (string) random_int(10000, 99999), 'plate_code' => 'A',
            'vehicle_make_id' => $make->id, 'year' => 2020, 'odometer' => 50000, 'vehicle_type' => 'car']);

        return $this->jobs->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'customer_complaint' => 'Noise',
            'department_id' => Department::query()->where('code', 'MECH')->value('id')]);
    }

    /** Quotation with one service + one stocked part, approved by the customer. */
    protected function approvedJob(): array
    {
        $jc = $this->newJob();
        $part = $this->makePart();
        app(StockService::class)->receive($part, $this->mainWarehouse()->id, 5, 10, ['type' => 'opening']);
        $service = Service::create(['code' => 'S1', 'name_ar' => 'خدمة', 'name_en' => 'Service', 'department_id' => $jc->department_id,
            'price' => 12, 'estimated_minutes' => 60, 'tax_rate' => 5]);
        $quotes = app(QuotationService::class);
        $q = $quotes->create(['job_card_id' => $jc->id, 'customer_id' => $jc->customer_id, 'vehicle_id' => $jc->vehicle_id], [
            ['item_type' => 'service', 'service_id' => $service->id, 'description' => 'Service', 'qty' => 1, 'unit_price' => 12, 'tax_rate' => 5],
            ['item_type' => 'part', 'part_id' => $part->id, 'description' => 'Part', 'qty' => 2, 'unit_price' => 20, 'tax_rate' => 5],
        ]);
        $quotes->send($q, []);
        $quotes->respond($q, $q->items->mapWithKeys(fn ($i) => [$i->id => 'approved'])->all(), 'Customer', 'in_person');

        return [$jc->fresh(), $part];
    }

    public function test_job_card_gets_number_and_initial_status(): void
    {
        $jc = $this->newJob();
        $this->assertSame(JobCard::PENDING_INSPECTION, $jc->status);
        $this->assertMatchesRegularExpression('/^[A-Z]+-\d{4}-\d{6}$/', $jc->number);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $jc = $this->newJob();
        $this->actAsRole('Technician');
        $this->expectException(BusinessException::class);
        $this->jobs->changeStatus($jc, JobCard::DELIVERED);
    }

    public function test_work_cannot_start_without_customer_approval(): void
    {
        $jc = $this->newJob();
        $this->jobs->changeStatus($jc, JobCard::AWAITING_QUOTATION);
        $this->actAsRole('Technician');
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessageMatches('/quotation is required/');
        $this->jobs->changeStatus($jc->fresh(), JobCard::APPROVED);
    }

    public function test_full_cycle_from_quotation_to_delivery(): void
    {
        Settings::set(['workshop.require_payment_before_delivery' => true]);
        [$jc, $part] = $this->approvedJob();
        $this->assertSame(JobCard::APPROVED, $jc->status);
        $this->assertCount(1, $jc->tasks);
        $this->assertCount(1, $jc->parts);

        // issue parts -> stock leaves, cost goes to WIP
        foreach ($jc->parts as $p) {
            $this->jobs->issuePart($p);
        }
        $this->assertMoney(3, StockLevel::query()->where('part_id', $part->id)->value('qty_on_hand'));
        $this->assertMoney(20, $this->balance('wip'));

        foreach ($jc->tasks()->get() as $t) {
            $this->jobs->startTask($t, $this->admin->id);
            $this->jobs->finishTask($t->fresh());
        }

        // delivery requires QC and an invoice
        try {
            $this->jobs->changeStatus($jc->fresh(), JobCard::DELIVERED);
            $this->fail('Delivered without QC / invoice');
        } catch (BusinessException) {
        }

        $this->jobs->qualityCheck($jc->fresh(), 'pass', [], 'OK');
        $this->assertSame(JobCard::READY, $jc->fresh()->status);

        $inv = app(InvoiceService::class)->post(app(InvoiceService::class)->fromJobCard($jc->fresh()));
        $this->assertMoney('54.600', $inv->total); // (12 + 40) * 1.05
        $this->assertMoney(0, $this->balance('wip'));
        $this->assertMoney(20, $this->balance('cogs'));

        // cash customers must pay before delivery
        try {
            $this->jobs->changeStatus($jc->fresh(), JobCard::DELIVERED);
            $this->fail('Delivered with an unpaid balance');
        } catch (BusinessException) {
        }
        app(PaymentService::class)->receiveFromCustomer($jc->customer, $inv->total, $this->cashAccountId(), 'cash');
        $this->jobs->changeStatus($jc->fresh(), JobCard::DELIVERED);
        $this->assertSame(JobCard::DELIVERED, $jc->fresh()->status);
        $this->assertLedgerBalanced();
    }

    public function test_guide_follows_the_job_from_reception_to_delivery(): void
    {
        Settings::set(['workshop.require_payment_before_delivery' => true]);
        $guide = app(\App\Modules\Workshop\Services\JobCardGuide::class);
        $phase = fn (JobCard $jc) => $guide->build($jc->fresh())['current'];
        $action = fn (JobCard $jc, string $status) => collect($guide->build($jc->fresh())['actions'])->firstWhere('status', $status);

        $this->assertSame('inspection', $phase($this->newJob()));

        [$jc] = $this->approvedJob();
        $g = $guide->build($jc->fresh());
        $this->assertSame('parts', $g['current']);
        $this->assertSame(4, $g['number']);
        $this->assertSame(['done', 'done', 'done', 'current'], array_slice(array_column($g['phases'], 'state'), 0, 4));

        foreach ($jc->parts as $p) {
            $this->jobs->issuePart($p);
        }
        $task = $jc->tasks()->first();
        $this->jobs->startTask($task, $this->admin->id);
        $this->assertSame('work', $phase($jc));
        // cannot send to QC before the tasks are finished: the button is disabled with the reason
        $this->assertTrue($action($jc, JobCard::QUALITY_INSPECTION)['disabled']);

        $this->jobs->finishTask($task->fresh());
        $this->assertFalse($action($jc, JobCard::QUALITY_INSPECTION)['disabled']);

        $this->jobs->qualityCheck($jc->fresh(), 'pass', [], 'OK');
        $this->assertSame('invoice', $phase($jc));
        $deliver = $action($jc, JobCard::DELIVERED);
        $this->assertTrue($deliver['disabled']);
        $this->assertStringContainsString('invoiced', $deliver['reason']);

        $inv = app(InvoiceService::class)->post(app(InvoiceService::class)->fromJobCard($jc->fresh()));
        $this->assertTrue($action($jc, JobCard::DELIVERED)['disabled']); // unpaid cash customer
        app(PaymentService::class)->receiveFromCustomer($jc->customer, $inv->total, $this->cashAccountId(), 'cash');
        $this->assertFalse($action($jc, JobCard::DELIVERED)['disabled']);

        $this->jobs->changeStatus($jc->fresh(), JobCard::DELIVERED);
        $this->assertSame('delivery', $phase($jc));
        $this->jobs->changeStatus($jc->fresh(), JobCard::COMPLETED);
        $this->assertTrue($guide->build($jc->fresh())['finished']);
    }

    public function test_job_card_page_shows_the_guide(): void
    {
        $jc = $this->newJob();
        tenant()->forget();
        $this->get('/job-cards/'.$jc->id)->assertOk()
            ->assertSee('jc-steps', false)
            ->assertSee(__('guide.phases.inspection.title'))
            ->assertSee(__('guide.act.start_inspection'));
    }

    public function test_parts_cannot_be_issued_beyond_stock(): void
    {
        [$jc] = $this->approvedJob();
        $p = $jc->parts->first();
        $p->update(['qty' => 50]);

        $this->expectException(BusinessException::class);
        $this->jobs->issuePart($p->fresh(), 50);
    }

    public function test_cancelling_requires_returning_issued_parts(): void
    {
        [$jc] = $this->approvedJob();
        $this->jobs->issuePart($jc->parts->first());

        try {
            $this->jobs->changeStatus($jc->fresh(), JobCard::CANCELLED);
            $this->fail('Cancelled with issued parts');
        } catch (BusinessException) {
        }
        $this->jobs->returnPart($jc->parts()->first(), 2);
        $this->jobs->changeStatus($jc->fresh(), JobCard::CANCELLED);
        $this->assertSame(JobCard::CANCELLED, $jc->fresh()->status);
        $this->assertMoney(0, $this->balance('wip'));
    }
}
