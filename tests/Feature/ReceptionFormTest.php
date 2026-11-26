<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\Customer;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Workshop\Models\JobCard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Vehicle reception keeps everything the user entered when the submit is refused. */
class ReceptionFormTest extends TestCase
{
    protected \App\Modules\Core\Models\Company $company;

    protected function inTenant(callable $fn): mixed
    {
        return tenant()->run($this->company->id, $fn);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = $this->makeTenant();
        $this->app->setLocale('ar');
    }

    public function test_failed_submit_keeps_existing_customer_vehicle_and_condition(): void
    {
        [$customer, $vehicle] = $this->inTenant(function () {
            $c = Customer::create(['code' => 'C-9001', 'name' => 'Khalid Al Busaidi', 'phone' => '+96891112222', 'type' => 'individual']);
            $v = Vehicle::create(['customer_id' => $c->id, 'plate_number' => '7788', 'plate_code' => 'KS', 'plate_country' => 'OM', 'vehicle_type' => 'car']);

            return [$c, $v];
        });

        // reported problems missing -> refused
        $this->from('/reception')->post('/reception', [
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'odometer' => 123456, 'fuel_level' => '62.5',
            'damages' => [['area' => 'front', 'type' => 'dent', 'description' => 'Front bumper', 'x' => 20, 'y' => 15]],
            'personal_items' => ['Sunglasses'], 'checklist' => ['keys' => '1'], 'priority' => 'high', 'notes' => 'Call before repair',
            '_idem' => 'rx-1',
        ])->assertRedirect('/reception')->assertSessionHasErrors('reported_issues');

        $page = $this->get('/reception')->assertOk();
        $page->assertSee('Khalid Al Busaidi — +96891112222')      // customer lookup text, not only the hidden id
            ->assertSee('7788', false)                            // vehicle lookup text
            ->assertSee("odometer: '123456'", false)                        // odometer
            ->assertSee('Front bumper', false)                    // damages
            ->assertSee('Sunglasses', false)                      // personal items
            ->assertSee('Call before repair')
            ->assertSee('value="high" selected', false)
            ->assertSee('id="cl_keys" checked', false)
            ->assertSee('يرجى إرفاقها مرة أخرى');                   // photos must be attached again
        $this->assertSame(0, $this->inTenant(fn () => JobCard::query()->count()));
    }

    public function test_failed_submit_keeps_new_customer_and_new_vehicle(): void
    {
        $this->from('/reception')->post('/reception', [
            'customer_name' => 'Walk In Saeed', 'customer_phone' => '92223344', 'customer_type' => 'company',
            'plate_number' => '4455', 'plate_code' => 'ab', 'vin' => 'BADVIN', 'color' => 'White',
            'odometer' => 5000, 'fuel_level' => 50, 'reported_issues' => 'AC not cooling', '_idem' => 'rx-2',
        ])->assertSessionHasErrors('vin');

        $this->get('/reception')->assertOk()
            ->assertSee("customerMode: 'new'", false)->assertSee("vehicleMode: 'new'", false)
            ->assertSee('value="Walk In Saeed"', false)->assertSee('value="4455"', false)->assertSee('value="White"', false)
            ->assertSee('value="company" selected', false)->assertSee('AC not cooling');
    }

    public function test_photo_errors_are_explained_and_valid_submit_still_works(): void
    {
        Storage::fake('local');
        $this->from('/reception')->post('/reception', [
            'customer_name' => 'Walk In Saeed', 'customer_phone' => '92223344', 'plate_number' => '4455',
            'odometer' => 5000, 'fuel_level' => 50, 'reported_issues' => 'AC not cooling', '_idem' => 'rx-3',
            'photos' => [UploadedFile::fake()->create('scan.pdf', 50, 'application/pdf')],
        ])->assertSessionHasErrors(['photos.0' => 'يمكن إرفاق الصور فقط (JPG أو PNG أو WEBP).']);

        $this->post('/reception', [
            'customer_name' => 'Walk In Saeed', 'customer_phone' => '92223344', 'plate_number' => '4455',
            'odometer' => 5000, 'fuel_level' => 50, 'reported_issues' => 'AC not cooling', '_idem' => 'rx-4',
            'photos' => [UploadedFile::fake()->image('front.jpg', 800, 600)],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, $this->inTenant(fn () => JobCard::query()->count()));
    }

    public function test_vehicle_with_open_job_card_is_explained_and_can_be_overridden(): void
    {
        [$customer, $vehicle] = $this->inTenant(function () {
            $c = Customer::create(['code' => 'C-9002', 'name' => 'Marwan Mohammed', 'phone' => '+96891113333', 'type' => 'individual']);

            return [$c, Vehicle::create(['customer_id' => $c->id, 'plate_number' => '49069', 'plate_code' => 'AA', 'plate_country' => 'OM', 'vehicle_type' => 'car'])];
        });
        $data = ['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'odometer' => 88000, 'fuel_level' => 50, 'reported_issues' => 'Brake noise'];

        $this->post('/reception', $data + ['_idem' => 'oj-1'])->assertRedirect();
        $first = $this->inTenant(fn () => JobCard::query()->firstOrFail());

        // second reception of the same vehicle: kept on the form with a clear explanation and a link to the open job card
        $this->from('/reception')->post('/reception', $data + ['_idem' => 'oj-2'])->assertRedirect('/reception')->assertSessionHas('open_job_card');
        $this->get('/reception')->assertOk()
            ->assertSee('هذه المركبة موجودة حالياً في الورشة.')->assertSee($first->number)
            ->assertSee(route('job-cards.show', $first), false)->assertSee('name="allow_duplicate"', false)
            ->assertSee('Brake noise');
        $this->assertSame(1, $this->inTenant(fn () => JobCard::query()->count()));

        // the user confirms an additional job card
        $this->post('/reception', $data + ['allow_duplicate' => '1', '_idem' => 'oj-3'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $this->inTenant(fn () => JobCard::query()->count()));
    }

    public function test_unexpected_server_error_is_shown_on_the_form_with_a_reference(): void
    {
        $this->mock(\App\Modules\Workshop\Services\ReceptionService::class, function ($m) {
            $m->shouldReceive('checkIn')->andThrow(new \RuntimeException('SQLSTATE[22001]: Data too long for column plate_code'));
        });
        $data = ['customer_name' => 'Walk In', 'customer_phone' => '92223344', 'plate_number' => '777', 'odometer' => 10, 'fuel_level' => 50, 'reported_issues' => 'Noise'];

        $this->from('/reception')->post('/reception', $data + ['_idem' => 'se-1'])->assertRedirect('/reception')->assertSessionHas('error');
        $this->get('/reception')->assertOk()
            ->assertSee('لم يتم استلام المركبة. السبب:')
            ->assertSee('تعذر استلام المركبة بسبب خطأ في الخادم')
            ->assertSee('Data too long for column plate_code')   // company admin sees the technical detail
            ->assertSee('value="Walk In"', false);
    }

    public function test_expired_session_returns_to_the_form_with_the_data(): void
    {
        $this->app->instance(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, new class(app(), app('encrypter')) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
            public function handle($request, \Closure $next)
            {
                if ($request->isMethodSafe()) {
                    return $next($request);
                }
                throw new \Illuminate\Session\TokenMismatchException('CSRF token mismatch.');
            }
        });
        $this->from('/reception')->post('/reception', ['customer_name' => 'Late Customer', 'reported_issues' => 'Oil leak'])
            ->assertRedirect('/reception')->assertSessionHas('error');
        $this->get('/reception')->assertOk()->assertSee('انتهت الجلسة')->assertSee('value="Late Customer"', false)->assertSee('Oil leak');
    }

    public function test_failed_customer_message_does_not_hide_the_new_job_card(): void
    {
        $this->mock(\App\Modules\Core\Services\NotificationService::class, function ($m) {
            $m->shouldReceive('notifyCustomer')->andThrow(new \RuntimeException('SMS gateway not configured'));
        });
        $this->post('/reception', ['customer_name' => 'Walk In', 'customer_phone' => '92223344', 'plate_number' => '778', 'odometer' => 10,
            'fuel_level' => 50, 'reported_issues' => 'Noise', 'notify' => '1', '_idem' => 'nf-1'])
            ->assertRedirect()->assertSessionHas('success')->assertSessionHas('warning');
        $this->assertSame(1, $this->inTenant(fn () => JobCard::query()->count()));
    }

    public function test_refused_permission_is_explained_on_the_form(): void
    {
        $this->actAsRole('Technician'); // technicians cannot receive vehicles
        $this->from('/job-cards')->post('/reception', ['customer_name' => 'Walk In', 'reported_issues' => 'Noise', '_idem' => 'pm-1'])
            ->assertRedirect('/job-cards')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'reception.create') && str_contains($m, 'Technician'));
    }

    public function test_request_larger_than_post_max_size_returns_to_the_form(): void
    {
        $this->from('/reception')->withServerVariables(['CONTENT_LENGTH' => (string) (1024 ** 4)])
            ->post('/reception', [])->assertRedirect('/reception')->assertSessionHas('error');
    }
}
