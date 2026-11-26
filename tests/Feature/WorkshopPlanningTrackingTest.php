<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\HR\Models\Employee;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\TestDrive;
use App\Modules\Workshop\Models\WorkshopBay;
use App\Modules\Workshop\Models\YardLocation;
use App\Modules\Workshop\Services\CapacityService;
use App\Modules\Workshop\Services\JobCardService;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkshopPlanningTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    protected function technician(string $name): User
    {
        static $n = 0;
        $n++;
        $u = new User(['name' => $name, 'email' => 'tech'.$n.'@acme.test', 'password' => 'Secret@12345']);
        $u->company_id = $this->company->id;
        $u->is_active = true;
        $u->save();
        Employee::create(['user_id' => $u->id, 'code' => 'T-'.$n, 'name' => $name, 'is_technician' => true]);

        return $u;
    }

    protected function job(array $tasks = [90, 60]): JobCard
    {
        $customer = $this->makeCustomer();
        $v = Vehicle::create(['customer_id' => $customer->id, 'plate_number' => (string) random_int(10000, 99999), 'plate_code' => 'A', 'vehicle_type' => 'car', 'odometer' => 40000]);
        $svc = app(JobCardService::class);
        $jc = $svc->create(['customer_id' => $customer->id, 'vehicle_id' => $v->id, 'customer_complaint' => 'Service', 'odometer_in' => 40000]);
        foreach ($tasks as $i => $min) {
            $svc->addTask($jc, ['description' => 'Task '.($i + 1), 'unit_price' => 10, 'estimated_minutes' => $min]);
        }

        return $jc->fresh();
    }

    public function test_auto_schedule_books_free_technicians_and_bays_without_clashes(): void
    {
        $t1 = $this->technician('Ahmed');
        $t2 = $this->technician('Badr');
        $b1 = WorkshopBay::create(['code' => 'L1', 'name' => 'Lift 1', 'type' => 'lift', 'has_lift' => true, 'is_active' => true]);
        WorkshopBay::create(['code' => 'L2', 'name' => 'Lift 2', 'type' => 'lift', 'is_active' => true]);
        $cap = app(CapacityService::class);

        $a = $this->job([90, 60]);
        $b = $this->job([120]);
        $c = $this->job([30]);
        foreach ([$a, $b, $c] as $jc) {
            $this->assertNotEmpty($cap->autoSchedule($jc));
        }
        $tasks = \App\Modules\Workshop\Models\JobCardTask::query()->whereNotNull('scheduled_start')->get();
        $this->assertCount(4, $tasks);
        // no technician or bay is booked twice at the same time
        foreach ($tasks as $x) {
            foreach ($tasks as $y) {
                if ($x->id >= $y->id || ! ($x->scheduled_start < $y->scheduled_end && $y->scheduled_start < $x->scheduled_end)) {
                    continue;
                }
                $this->assertNotSame($x->technician_id, $y->technician_id, 'technician double-booked');
                $this->assertNotSame($x->bay_id, $y->bay_id, 'bay double-booked');
                $this->assertNotSame($x->job_card_id, $y->job_card_id, 'one vehicle on two tasks at once');
            }
            $this->assertSame((int) ($x->estimated_minutes ?: 60), (int) $x->scheduled_start->diffInMinutes($x->scheduled_end));
        }
        // tasks of one vehicle follow each other and the job card gets its estimated completion
        [$first, $second] = $a->tasks()->orderBy('id')->get()->all();
        $this->assertTrue($second->scheduled_start->gte($first->scheduled_end));
        $this->assertEquals($second->scheduled_end, $a->fresh()->estimated_completion_at);
        $this->assertNotNull($a->fresh()->bay_id);

        // manual booking that clashes is refused; an appointment on a booked bay is refused too
        $busy = $tasks->first();
        try {
            $cap->scheduleTask($c->tasks()->first(), $busy->technician_id, null, $busy->scheduled_start->copy());
            $this->fail('clash not detected');
        } catch (BusinessException) {
        }
        $this->expectException(BusinessException::class);
        app(AppointmentService::class)->assertAvailable($busy->scheduled_start->copy(), $busy->scheduled_end->copy(), ['bay_id' => $busy->bay_id]);
    }

    public function test_planner_pages_and_utilization(): void
    {
        $this->technician('Ahmed');
        WorkshopBay::create(['code' => 'L1', 'name' => 'Lift 1', 'type' => 'lift', 'is_active' => true]);
        $jc = $this->job([60]);
        $this->get('/workshop/planner')->assertOk()->assertSee(__('Waiting to be scheduled'))->assertSee($jc->number);
        $this->post('/workshop/planner/'.$jc->id.'/auto')->assertSessionHas('success');
        $task = $jc->tasks()->first();
        $this->assertNotNull($task->scheduled_start);
        $this->get('/workshop/planner?date='.$task->scheduled_start->toDateString())->assertOk()->assertSee('plan-block', false)->assertSee($jc->vehicle->plate());
        $this->get('/workshop/planner?view=technicians&period=month')->assertOk()->assertSee('Ahmed');
        $this->get('/job-cards/'.$jc->id)->assertOk()->assertSee(__('Planning & tracking'))->assertSee($jc->fresh()->estimated_completion_at->format('Y-m-d H:i'));

        // time worked against the booked hours
        $task->update(['status' => 'done', 'finished_at' => now(), 'actual_minutes' => 50]);
        $u = app(CapacityService::class)->utilization(now()->subDays(7), now()->addDays(7));
        $this->assertGreaterThan(0, $u['available']);
        $this->assertCount(1, $u['bays']);
        $this->assertGreaterThan(0, collect($u['technicians'])->sum('booked'));

        // unschedule
        $task->update(['status' => 'pending']);
        $this->delete('/workshop/planner/tasks/'.$task->id)->assertSessionHas('success');
        $this->assertNull($task->fresh()->scheduled_start);
    }

    public function test_live_screen_and_tv_link(): void
    {
        $jc = $this->job([30]);
        $jc->update(['status' => JobCard::IN_PROGRESS, 'promised_at' => now()->subHour()]);
        $this->get('/workshop/live')->assertOk()->assertSee($jc->vehicle->plate())->assertSee('tvBoard', false);
        $this->get('/workshop/live?partial=1')->assertOk()->assertSee($jc->vehicle->plate())->assertDontSee('<html', false);

        $this->get('/tv/'.str_repeat('x', 40))->assertNotFound();
        $this->post('/workshop/live/token')->assertSessionHas('success');
        $token = Settings::get('workshop_tv_token');
        $this->assertSame(48, strlen($token));
        auth()->logout();
        $this->get('/tv/'.$token)->assertOk()->assertSee($jc->vehicle->plate())
            ->assertDontSee($jc->customer->name);              // full name hidden on the public screen
    }

    public function test_vehicle_key_tracking_and_test_drive(): void
    {
        $jc = $this->job([30]);
        $p1 = YardLocation::create(['code' => 'P-01', 'name' => 'Parking 1', 'type' => 'parking', 'is_active' => true]);
        $tech = $this->technician('Salem');

        $this->get('/job-cards/'.$jc->id.'/key-tag')->assertOk()->assertSee($jc->fresh()->key_tag);
        $tag = $jc->fresh()->key_tag;
        $this->assertMatchesRegularExpression('/^K\d{6}$/', $tag);

        // scanning the tag (RFID / barcode) or the QR url opens the vehicle
        $this->post('/workshop/tracking/scan', ['code' => $tag, 'via' => 'rfid'])->assertRedirect(route('tracking.card', [$jc, 'via' => 'rfid']));
        $this->post('/workshop/tracking/scan', ['code' => route('tracking.card', $jc)])->assertRedirect();
        $this->post('/workshop/tracking/scan', ['code' => 'NOPE'])->assertSessionHas('error');

        $this->post('/workshop/tracking/'.$jc->id.'/move', ['yard_location_id' => $p1->id, 'via' => 'qr'])->assertSessionHas('success');
        $this->post('/workshop/tracking/'.$jc->id.'/key-in', ['key_hook' => 'a-12'])->assertSessionHas('success');
        $other = $this->job([30]);
        $this->post('/workshop/tracking/'.$other->id.'/key-in', ['key_hook' => 'A-12'])->assertSessionHas('error');   // hook taken
        $this->post('/workshop/tracking/'.$jc->id.'/key-out', ['holder_id' => $tech->id])->assertSessionHas('success');
        $jc->refresh();
        $this->assertSame($p1->id, $jc->yard_location_id);
        $this->assertSame($tech->id, $jc->key_holder_id);
        $this->assertNull($jc->key_hook);
        $this->assertSame(['tagged', 'moved', 'key_in', 'key_out'], $jc->trackingEvents()->orderBy('id')->pluck('type')->all());
        $this->get('/workshop/tracking')->assertOk()->assertSee('P-01')->assertSee($jc->vehicle->plate())->assertSee('Salem');

        // test drive: permit, out, back — odometer recorded and checked
        $this->post('/job-cards/'.$jc->id.'/test-drives', ['driver_id' => $tech->id, 'purpose' => 'Noise check', 'max_km' => 5, 'customer_consent' => 1])->assertSessionHas('print_voucher');
        $td = TestDrive::query()->firstOrFail();
        $this->post('/job-cards/'.$jc->id.'/test-drives', ['driver_id' => $tech->id])->assertSessionHas('error');        // one open permit
        $this->post('/test-drives/'.$td->id.'/start', ['odometer' => 100])->assertSessionHas('error');                // below odometer in
        $this->post('/test-drives/'.$td->id.'/start', ['odometer' => 40010, 'fuel' => 50])->assertSessionHas('success');
        $this->post('/test-drives/'.$td->id.'/finish', ['odometer' => 40000])->assertSessionHas('error');
        $this->post('/test-drives/'.$td->id.'/finish', ['odometer' => 40022, 'notes' => 'OK'])->assertSessionHas('warning');   // 12 km > 5
        $td->refresh();
        $this->assertSame('returned', $td->status);
        $this->assertSame(12, $td->distance());
        $this->assertSame(40022, $jc->vehicle->fresh()->odometer);
        $this->get('/test-drives/'.$td->id.'/print')->assertOk()->assertSee($td->number)->assertSee('12 km');
        $this->get('/workshop/tracking/'.$jc->id)->assertOk()->assertSee($td->number);
    }
}
