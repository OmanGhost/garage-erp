<?php

namespace Tests\Feature;

use App\Modules\Inventory\Models\PartCompatibility;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Vehicles\Models\VehicleMake;
use App\Modules\Vehicles\Models\VehicleModel;
use App\Modules\Workshop\Models\HandoverAuthorization;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\VehicleGateLog;
use App\Modules\Workshop\Services\HandoverService;
use Tests\TestCase;

class HandoverAndGateTest extends TestCase
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

    /** A job card ready for delivery (workflow forced, nothing invoiced). */
    protected function readyJob(): JobCard
    {
        $this->post('/reception', ['customer_name' => 'Nasser Al Kindi', 'customer_phone' => '92334455', 'plate_number' => '8899', 'plate_code' => 'AR',
            'odometer' => 1000, 'fuel_level' => 50, 'reported_issues' => 'Brakes', '_idem' => uniqid()])->assertRedirect();

        return $this->inTenant(function () {
            $jc = JobCard::query()->latest('id')->firstOrFail();
            $jc->forceFill(['status' => JobCard::READY, 'job_type' => 'internal'])->save(); // internal: no invoice needed to deliver

            return $jc->fresh('vehicle', 'customer');
        });
    }

    public function test_vehicle_page_lists_compatible_parts_with_stock(): void
    {
        [$vehicle, $fits, $other] = $this->inTenant(function () {
            $make = VehicleMake::query()->firstOrCreate(['name' => 'Toyota']);
            $model = VehicleModel::query()->firstOrCreate(['vehicle_make_id' => $make->id, 'name' => 'Land Cruiser']);
            $customer = $this->makeCustomer();
            $v = Vehicle::create(['customer_id' => $customer->id, 'plate_number' => '1111', 'plate_country' => 'OM', 'vehicle_type' => 'car',
                'vehicle_make_id' => $make->id, 'vehicle_model_id' => $model->id, 'year' => 2019]);
            $fits = $this->makePart(['sku' => 'LC-OIL-FILTER', 'name_ar' => 'فلتر زيت لاندكروزر']);
            $old = $this->makePart(['sku' => 'LC-OLD-PART']);
            $other = $this->makePart(['sku' => 'NISSAN-PART']);
            PartCompatibility::create(['part_id' => $fits->id, 'vehicle_make_id' => $make->id, 'vehicle_model_id' => $model->id, 'year_from' => 2016, 'year_to' => 2022]);
            PartCompatibility::create(['part_id' => $old->id, 'vehicle_make_id' => $make->id, 'vehicle_model_id' => $model->id, 'year_from' => 2000, 'year_to' => 2010]);
            app(StockService::class)->receive($fits, $this->mainWarehouse()->id, 6, 3, ['type' => 'opening']);

            return [$v, $fits, $other];
        });

        $this->get('/vehicles/'.$vehicle->id)->assertOk()
            ->assertSee('القطع المتوافقة المتوفرة في النظام')->assertSee('LC-OIL-FILTER')->assertSee('2016–2022')
            ->assertDontSee('LC-OLD-PART')->assertDontSee('NISSAN-PART');
    }

    public function test_gate_log_page_manual_entry_and_print(): void
    {
        $jc = $this->readyJob();
        $this->get('/gate-log')->assertOk()->assertSee(__('Vehicle Gate Log'))->assertSee('8899');

        $this->post('/gate-log', ['direction' => 'out', 'vehicle_id' => $jc->vehicle_id, 'odometer' => 1010, 'person_name' => 'Road tester', 'notes' => 'Road test'])
            ->assertRedirect(route('gate-logs.index'))->assertSessionHas('print_voucher');
        $log = $this->inTenant(fn () => VehicleGateLog::query()->where('direction', 'out')->latest('id')->firstOrFail());
        $this->assertSame($jc->id, $log->job_card_id); // linked to the open job card automatically

        $this->get('/gate-log/'.$log->id.'/print')->assertOk()->assertSee('سجل حركة مركبة')->assertSee($log->reference())->assertSee('Road tester')->assertSee('8899');
        $this->get('/gate-log/'.$log->id.'/print/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/gate-log?direction=in')->assertOk()->assertDontSee('Road tester');
    }

    public function test_authorization_flow_signature_delivery_and_id_check(): void
    {
        $jc = $this->readyJob();

        // issue
        $this->post('/handover-authorizations', ['vehicle_id' => $jc->vehicle_id, 'job_card_id' => $jc->id, 'type' => 'pick_up', 'person_name' => 'Saif Driver',
            'id_type' => 'civil_id', 'id_number' => '12 345 678', 'relationship' => 'driver', 'single_use' => '1'])->assertRedirect();
        $a = $this->inTenant(fn () => HandoverAuthorization::query()->firstOrFail());
        $this->assertSame('pending', $a->status);
        $this->assertSame('12345678', $a->id_number);
        $this->assertMatchesRegularExpression('/^HA-\d{4}-\d{6}$/', $a->number);
        $this->get('/handover-authorizations/'.$a->id)->assertOk()->assertSee('بانتظار المالك')->assertSee('Saif Driver');
        $this->get('/handover-authorizations/'.$a->id.'/print')->assertOk()->assertSee('تفويض تسليم / استلام مركبة')->assertSee('Saif Driver')->assertSee($a->number);

        // a pending authorization cannot be used for delivery
        $this->get('/job-cards/'.$jc->id)->assertOk()->assertSee('لا يوجد تفويض استلام ساري');

        // owner signs the letter -> active
        $this->post('/handover-authorizations/'.$a->id.'/approve', ['method' => 'signature', 'signed' => '1'])->assertSessionHas('success');
        $this->assertSame('active', $a->fresh()->status);
        $this->get('/job-cards/'.$jc->id)->assertOk()->assertSee('Saif Driver');

        // wrong ID -> refused, vehicle not delivered
        $this->from('/job-cards/'.$jc->id)->post('/job-cards/'.$jc->id.'/status', ['status' => 'delivered', 'receiver' => 'authorized',
            'handover_authorization_id' => $a->id, 'receiver_id_number' => '99999999', '_idem' => uniqid()])->assertSessionHas('error');
        $this->assertSame(JobCard::READY, $jc->fresh()->status);

        // correct ID -> delivered to the authorized person, single use authorization is used, gate log records it
        $this->from('/job-cards/'.$jc->id)->post('/job-cards/'.$jc->id.'/status', ['status' => 'delivered', 'receiver' => 'authorized',
            'handover_authorization_id' => $a->id, 'receiver_id_number' => '12345678', '_idem' => uniqid()])
            ->assertSessionHas('print_voucher', route('job-cards.gate-pass', $jc));
        $jc->refresh();
        $this->assertSame(JobCard::DELIVERED, $jc->status);
        $this->assertSame('authorized', $jc->delivered_to_type);
        $this->assertSame('Saif Driver', $jc->delivered_to_name);
        $this->assertSame('used', $a->fresh()->status);
        $out = $this->inTenant(fn () => VehicleGateLog::query()->where('direction', 'out')->latest('id')->firstOrFail());
        $this->assertSame($a->id, $out->handover_authorization_id);

        $this->get('/job-cards/'.$jc->id.'/gate-pass')->assertOk()->assertSee('Saif Driver')->assertSee($a->number);
        $this->get('/handover-authorizations/'.$a->id)->assertOk()->assertSee($out->reference());
        $this->get('/gate-log')->assertOk()->assertSee('Saif Driver')->assertSee($a->number);
    }

    public function test_approval_code_flow_and_revocation(): void
    {
        $jc = $this->readyJob();
        $svc = app(HandoverService::class);
        $a = $this->inTenant(fn () => $svc->issue(['customer_id' => $jc->customer_id, 'vehicle_id' => $jc->vehicle_id, 'type' => 'both',
            'person_name' => 'Ali Brother', 'id_number' => 'AB-1234', 'single_use' => false]));

        $code = $this->inTenant(fn () => $svc->sendCode($a->fresh()));
        $this->post('/handover-authorizations/'.$a->id.'/approve', ['method' => 'code', 'code' => '000000'])->assertSessionHas('error');
        $this->assertSame(1, $a->fresh()->code_attempts);
        $this->post('/handover-authorizations/'.$a->id.'/approve', ['method' => 'code', 'code' => $code])->assertSessionHas('success');
        $this->assertSame('active', $a->fresh()->status);
        $this->assertSame('code', $a->fresh()->approval_method);

        // owner delivery is still possible and records the owner
        $this->post('/job-cards/'.$jc->id.'/status', ['status' => 'delivered', 'receiver' => 'owner', 'receiver_name' => 'Nasser Al Kindi', '_idem' => uniqid()]);
        $this->assertSame('owner', $jc->fresh()->delivered_to_type);
        $this->assertSame('active', $a->fresh()->status); // untouched

        $this->post('/handover-authorizations/'.$a->id.'/revoke', ['reason' => 'Owner request'])->assertSessionHas('success');
        $this->assertSame('revoked', $a->fresh()->status);
        $this->get('/handover-authorizations?status=revoked')->assertOk()->assertSee('Ali Brother');
    }

    public function test_reception_by_another_person_matches_a_drop_off_authorization(): void
    {
        // first visit creates the vehicle, delivered to the owner
        $jc = $this->readyJob();
        $this->post('/job-cards/'.$jc->id.'/status', ['status' => 'delivered', '_idem' => uniqid()]);
        $svc = app(HandoverService::class);
        $a = $this->inTenant(function () use ($svc, $jc) {
            $a = $svc->issue(['customer_id' => $jc->customer_id, 'vehicle_id' => $jc->vehicle_id, 'type' => 'drop_off', 'person_name' => 'Company Driver', 'id_number' => 'D-777']);

            return $svc->approveWithSignature($a);
        });

        $this->post('/reception', ['customer_id' => $jc->customer_id, 'vehicle_id' => $jc->vehicle_id, 'odometer' => 2000, 'fuel_level' => 50, 'reported_issues' => 'Service',
            'brought_by' => 'other', 'brought_by_name' => 'Someone', 'brought_by_id_number' => 'd 777', '_idem' => uniqid()])->assertRedirect();
        $in = $this->inTenant(fn () => VehicleGateLog::query()->where('direction', 'in')->latest('id')->firstOrFail());
        $this->assertSame($a->id, $in->handover_authorization_id);
        $this->assertSame('Company Driver', $in->person_name);
        $this->assertSame('used', $a->fresh()->status);
    }
}
