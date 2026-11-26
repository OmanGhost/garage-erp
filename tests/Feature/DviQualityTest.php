<?php

namespace Tests\Feature;

use App\Modules\Quality\Models\CapaAction;
use App\Modules\Quality\Models\Nonconformity;
use App\Modules\Quality\Models\QualityAudit;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Workshop\Models\Inspection;
use App\Modules\Workshop\Models\InspectionTemplate;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\Service;
use App\Modules\Workshop\Services\JobCardService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DviQualityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    protected function job(): JobCard
    {
        $customer = $this->makeCustomer(['name' => 'Khalid Al Rawahi']);
        $v = Vehicle::create(['customer_id' => $customer->id, 'plate_number' => (string) random_int(10000, 99999), 'plate_code' => 'B', 'vehicle_type' => 'car', 'odometer' => 30000]);

        return app(JobCardService::class)->create(['customer_id' => $customer->id, 'vehicle_id' => $v->id, 'customer_complaint' => 'Brake noise', 'odometer_in' => 30000]);
    }

    protected function png(): UploadedFile
    {
        $img = imagecreatetruecolor(40, 20);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);

        return UploadedFile::fake()->createWithContent('signature.png', (string) ob_get_clean());
    }

    public function test_digital_inspection_measurements_diagram_suggestions_and_customer_report(): void
    {
        $brakes = Service::create(['code' => 'BRK-01', 'name_ar' => 'تغيير فحمات', 'price' => 25, 'tax_rate' => 5, 'is_active' => true]);
        // checklist with a measurement: pads OK from 3 mm, linked to the brake service
        $this->post('/m/inspection-templates', ['name' => 'DVI', 'kind' => 'inspection', 'is_active' => 1, '_idem' => 'tpl-1',
            'items_text' => "Brakes | سماكة الفحمات | Pad thickness | mm | 3 | | BRK-01\nLights | الإضاءة | Lights"])->assertSessionHasNoErrors();
        $tpl = InspectionTemplate::query()->where('name', 'DVI')->firstOrFail();
        $this->assertSame('mm', $tpl->items()->orderBy('sort')->first()->unit);

        $jc = $this->job();
        $this->post('/job-cards/'.$jc->id.'/inspection', ['template_id' => $tpl->id])->assertRedirect();
        $ins = Inspection::query()->firstOrFail();
        $this->assertSame('before', $ins->stage);
        $pad = $ins->results()->where('unit', 'mm')->firstOrFail();

        // 2 mm is below the 3 mm limit -> attention (yellow); 2 mm < 80 % of 3 -> urgent (red)
        $this->put('/inspections/'.$ins->id, ['results' => [$pad->id => ['result' => 'ok', 'value' => '2.2']]])->assertSessionHas('success');
        $this->assertSame('urgent', $pad->fresh()->result);

        // suggested service from the red item, a finding on the diagram with a photo
        $this->post('/inspections/'.$ins->id.'/suggest')->assertSessionHas('success');
        $this->post('/inspections/'.$ins->id.'/suggest')->assertSessionHas('info');      // not twice
        $this->post('/inspections/'.$ins->id.'/findings', ['description' => 'Scratch on bumper', 'zone' => 'front_bumper', 'severity' => 'low', 'estimated_price' => 15,
            'photos' => [UploadedFile::fake()->image('bumper.jpg', 300, 200)]])->assertSessionHas('success');
        $ins->refresh()->load('findings');
        $this->assertCount(2, $ins->findings);
        $this->assertSame('25.000', $ins->findings->firstWhere('service_id', $brakes->id)->estimated_price);
        $this->assertSame(['front_bumper' => 'green'], $ins->zoneLights());
        $this->get('/inspections/'.$ins->id)->assertOk()->assertSee('dvi-zone', false)->assertSee('has-green', false);

        // technician signs; report sent to the customer
        $this->post('/inspections/'.$ins->id.'/sign', ['who' => 'technician', 'signature_file' => $this->png()])->assertSessionHas('success');
        $this->post('/inspections/'.$ins->id.'/send')->assertSessionHas('dvi_link');
        $ins->refresh();
        $this->assertNotNull($ins->technician_signed_at);
        $this->assertSame(40, strlen($ins->public_token));
        $this->assertDatabaseHas('notification_templates', ['event' => 'inspection.report', 'channel' => 'sms', 'company_id' => $this->company->id]);

        // the customer opens the link (no sign-in), sees photos, approves the brakes and declines the scratch, signs
        $brakeF = $ins->findings()->where('service_id', $brakes->id)->firstOrFail();
        $scratch = $ins->findings()->where('zone', 'front_bumper')->firstOrFail();
        $photo = $scratch->attachments()->firstOrFail();
        auth()->logout();
        $this->get('/dvi/'.$ins->public_token)->assertOk()->assertSee('Scratch on bumper')->assertSee(route('public.dvi.photo', [$ins->public_token, $photo->id]), false);
        $this->get('/dvi/'.$ins->public_token.'/photo/'.$photo->id)->assertOk();
        $this->get('/dvi/'.$ins->public_token.'/photo/999999')->assertNotFound();
        $this->post('/dvi/'.$ins->public_token.'/findings/'.$brakeF->id, ['decision' => 'approved'])->assertRedirect();
        $this->post('/dvi/'.$ins->public_token.'/findings/'.$scratch->id, ['decision' => 'declined'])->assertRedirect();
        $this->post('/dvi/'.$ins->public_token.'/sign', ['name' => 'Khalid', 'signature_file' => $this->png()])->assertRedirect();
        $this->get('/dvi/short')->assertNotFound();
        $ins->refresh();
        $this->assertNotNull($ins->customer_viewed_at);
        $this->assertSame('Khalid', $ins->customer_signed_name);

        // quotation only with the approved repair
        $this->actingAs($this->admin);
        $this->post('/inspections/'.$ins->id.'/quotation', ['_idem' => 'q-1'])->assertRedirect();
        $q = \App\Modules\Workshop\Models\Quotation::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $q->items()->count());
        $this->assertSame($brakes->id, $q->items()->first()->service_id);
        $this->get('/inspections/'.$ins->id.'/print')->assertOk()->assertSee('Approved');
    }

    public function test_before_after_comparison(): void
    {
        $this->post('/m/inspection-templates', ['name' => 'Basic', 'kind' => 'inspection', 'is_active' => 1, '_idem' => 'tpl-2', 'items_text' => "Tyres | الإطارات | Tyres\nOil | الزيت | Oil"]);
        $jc = $this->job();
        $this->post('/job-cards/'.$jc->id.'/inspection', ['template_id' => InspectionTemplate::query()->where('name', 'Basic')->value('id')])->assertRedirect();
        $before = Inspection::query()->firstOrFail();
        $tyres = $before->results()->where('label', 'الإطارات')->first();
        $this->put('/inspections/'.$before->id, ['results' => [$tyres->id => ['result' => 'urgent']]]);
        $this->post('/inspections/'.$before->id.'/complete', ['_idem' => 'c-1']);

        $this->post('/job-cards/'.$jc->id.'/inspection', ['stage' => 'after'])->assertRedirect();
        $after = Inspection::query()->where('stage', 'after')->firstOrFail();
        $this->assertSame($before->inspection_template_id, $after->inspection_template_id);
        $this->assertCount(2, $after->results);
        $this->get('/job-cards/'.$jc->id.'/inspections/compare')->assertOk()->assertSee(__('Improved'));
        $this->assertSame(JobCard::AWAITING_QUOTATION, $jc->fresh()->status);   // after-repair inspection does not move the job
    }

    public function test_quality_ncr_capa_audit_and_dashboard(): void
    {
        $jc = $this->job();
        // failed QC opens a nonconformity automatically
        app(JobCardService::class)->qualityCheck($jc, 'fail', [], 'Oil leak after service');
        $ncr = Nonconformity::query()->firstOrFail();
        $this->assertSame('qc_fail', $ncr->source);
        $this->assertSame($jc->id, $ncr->job_card_id);

        $this->get('/quality/ncr/'.$ncr->id)->assertOk()->assertSee($ncr->number);
        $this->post('/quality/ncr/'.$ncr->id.'/close')->assertSessionHas('error');               // no root cause yet
        $this->put('/quality/ncr/'.$ncr->id, ['category' => 'workmanship', 'severity' => 'major', 'root_cause' => 'Drain plug washer not replaced', 'analysis_method' => '5why'])->assertSessionHas('success');
        $this->assertSame('investigating', $ncr->fresh()->status);
        $this->post('/quality/ncr/'.$ncr->id.'/actions', ['type' => 'corrective', 'description' => 'Replace washer every oil change', 'due_date' => today()->addWeek()->toDateString()])->assertSessionHas('success');
        $this->post('/quality/ncr/'.$ncr->id.'/actions', ['type' => 'preventive', 'description' => 'Add washer to the oil service kit'])->assertSessionHas('success');
        $this->assertSame('action', $ncr->fresh()->status);
        [$a1, $a2] = CapaAction::query()->orderBy('id')->get()->all();
        $this->post('/quality/actions/'.$a1->id.'/verify', ['effective' => 1])->assertSessionHas('error');  // not done yet
        foreach ([$a1, $a2] as $a) {
            $this->post('/quality/actions/'.$a->id.'/complete')->assertSessionHas('success');
            $this->post('/quality/actions/'.$a->id.'/verify', ['effective' => 1, 'notes' => 'No leaks in 30 days'])->assertSessionHas('success');
        }
        $this->assertSame('verification', $ncr->fresh()->status);
        $this->post('/quality/ncr/'.$ncr->id.'/close')->assertSessionHas('success');
        $this->assertSame('closed', $ncr->fresh()->status);

        // internal audit: a nonconforming answer becomes an NCR
        $this->post('/m/quality-audits', ['title' => 'Workshop 5S', 'area' => 'Workshop', 'planned_date' => today()->toDateString(), '_idem' => 'au-1',
            'checklist_text' => "Tools stored in place\nFire extinguishers checked"])->assertSessionHasNoErrors();
        $audit = QualityAudit::query()->firstOrFail();
        $this->assertCount(2, $audit->checklist);
        $this->post('/quality/audits/'.$audit->id, ['answers' => [['result' => 'conform'], ['result' => 'nonconform', 'notes' => 'Expired']], 'complete' => 1])->assertSessionHas('success');
        $audit->refresh();
        $this->assertSame('completed', $audit->status);
        $this->assertEquals(50.0, (float) $audit->score);
        $this->assertSame(1, Nonconformity::query()->where('quality_audit_id', $audit->id)->count());

        // SOP and dashboard
        $this->post('/m/quality-sops', ['code' => 'SOP-OIL', 'title' => 'Oil change', 'version' => '1.0', 'status' => 'active', 'review_date' => today()->addDays(10)->toDateString(), '_idem' => 'sop-1'])->assertSessionHasNoErrors();
        $this->get('/quality')->assertOk()->assertSee(__('First-time fix'))->assertSee('SOP-OIL')->assertSee(status_label('workmanship'));
        $this->get('/m/nonconformities')->assertOk()->assertSee($ncr->number);
        $this->get('/m/capa-actions')->assertOk();
    }
}
