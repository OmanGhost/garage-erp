<?php

namespace Tests\Feature;

use App\Modules\Workshop\Models\JobCard;
use Tests\TestCase;

class ErpDoctorTest extends TestCase
{
    public function test_doctor_checks_the_server_and_replays_a_reception_without_saving(): void
    {
        $company = $this->makeTenant();
        tenant()->forget();
        auth()->logout();

        $this->artisan('erp:doctor', ['--user' => 'admin@acme.test'])
            ->expectsOutputToContain('extension bcmath')
            ->expectsOutputToContain('permission reception.create')
            ->expectsOutputToContain('reception accepted')
            ->assertSuccessful();

        $this->assertSame(0, tenant()->run($company->id, fn () => JobCard::query()->count()));
    }

    public function test_doctor_reports_why_a_user_cannot_receive_vehicles(): void
    {
        $this->makeTenant();
        $tech = $this->actAsRole('Technician');
        tenant()->forget();
        auth()->logout();

        $this->artisan('erp:doctor', ['--user' => $tech->email])
            ->expectsOutputToContain('the user cannot receive vehicles')
            ->expectsOutputToContain('reception.create')
            ->assertFailed();
    }
}
