<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppVersion;
use Tests\TestCase;

class VersionFooterTest extends TestCase
{
    public function test_version_is_shown_on_every_system_area(): void
    {
        $version = 'v'.AppVersion::version();
        $this->assertMatchesRegularExpression('/^v\d+\.\d+\.\d+$/', $version);

        $this->get('/login')->assertOk()->assertSee($version);

        $this->makeTenant();
        tenant()->forget();
        $this->get('/dashboard')->assertOk()->assertSee($version);

        $customer = $this->makeCustomerUser();
        $this->actingAs($customer)->get('/portal')->assertOk()->assertSee($version);

        $super = new User(['name' => 'Platform', 'email' => 'super@platform.test', 'password' => 'Secret@12345']);
        $super->is_super_admin = true;
        $super->is_active = true;
        $super->save();
        $this->actingAs($super)->get('/admin')->assertOk()->assertSee($version);
    }

    protected function makeCustomerUser(): User
    {
        tenant()->set($this->company->id);
        $c = $this->makeCustomer();
        $u = new User(['name' => 'Portal', 'email' => 'portal@acme.test', 'password' => 'Secret@12345']);
        $u->company_id = $this->company->id;
        $u->customer_id = $c->id;
        $u->is_active = true;
        $u->save();
        setPermissionsTeamId($this->company->id);
        $u->syncRoles(['Customer']);
        tenant()->forget();

        return $u;
    }
}
