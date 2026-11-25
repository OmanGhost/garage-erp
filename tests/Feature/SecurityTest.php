<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CRM\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    public function test_a_tenant_cannot_see_or_open_another_tenants_records(): void
    {
        $a = $this->makeTenant('alpha', 'admin@alpha.test');
        $secret = $this->makeCustomer(['name' => 'Alpha Secret Customer']);

        $this->makeTenant('beta', 'admin@beta.test');
        // model scope
        $this->assertNull(Customer::query()->find($secret->id));
        $this->assertFalse(Customer::query()->where('name', 'Alpha Secret Customer')->exists());

        // HTTP: route model binding and generic CRUD both 404
        tenant()->forget();
        $this->get('/customers/'.$secret->id)->assertNotFound();
        $this->get('/m/customers/'.$secret->id.'/edit')->assertNotFound();
        $this->get('/m/customers?q=Alpha')->assertOk()->assertDontSee('Alpha Secret Customer');

        // company_id cannot be changed to move data across tenants
        tenant()->set($a->id);
        $secret->company_id = 999;
        $this->expectException(\Throwable::class);
        $secret->save();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/m/customers')->assertRedirect('/login');
        $this->getJson('/api/v1/customers')->assertUnauthorized();
    }

    public function test_roles_restrict_access(): void
    {
        $this->makeTenant();
        tenant()->forget();
        $this->actAsRole('Technician');

        $this->get('/dashboard')->assertOk();
        $this->get('/accounting/journal/create')->assertForbidden();
        $this->get('/m/customers/create')->assertForbidden();
        $this->get('/settings')->assertForbidden();
    }

    public function test_portal_customers_cannot_reach_staff_pages_or_other_customers_data(): void
    {
        $this->makeTenant();
        $mine = $this->makeCustomer();
        $other = $this->makeCustomer(['name' => 'Someone Else']);
        $u = new User(['name' => 'Portal', 'email' => 'portal@acme.test', 'password' => 'Secret@12345']);
        $u->company_id = $this->company->id;
        $u->customer_id = $mine->id;
        $u->is_active = true;
        $u->save();
        setPermissionsTeamId($this->company->id);
        $u->syncRoles(['Customer']);
        tenant()->forget();
        Auth::forgetUser();

        $this->actingAs($u);
        $this->get('/portal')->assertOk()->assertDontSee('Someone Else');
        $this->get('/dashboard')->assertRedirect();
        $this->get('/m/customers')->assertRedirect();
        $this->get('/customers/'.$other->id)->assertRedirect();
    }

    public function test_duplicate_form_submissions_are_processed_once(): void
    {
        $this->makeTenant();
        tenant()->forget();
        $payload = ['code' => 'DUP-1', 'name' => 'Double Click', 'phone' => '99112233', 'type' => 'individual', '_idem' => 'same-token-123'];

        $this->post('/m/customers', $payload)->assertRedirect();
        $this->post('/m/customers', $payload)->assertRedirect()->assertSessionHas('warning');

        tenant()->set($this->company->id);
        $this->assertSame(1, Customer::query()->where('name', 'Double Click')->count());
    }

    public function test_tenant_admins_cannot_download_whole_platform_backups(): void
    {
        $this->makeTenant('alpha', 'admin@alpha.test');
        tenant()->forget();
        // single company (on-premise): its admin manages backups
        $this->get('/backups')->assertOk();

        // multi-tenant SaaS: the dump contains other companies' data
        $this->makeTenant('beta', 'admin@beta.test');
        tenant()->forget();
        $this->get('/backups')->assertForbidden();
        $this->get('/backups/backup-20260101-000000.zip')->assertForbidden();
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_login_records_history_and_rejects_bad_passwords(): void
    {
        $this->makeTenant();
        tenant()->forget();
        Auth::forgetUser();
        $this->app['auth']->forgetGuards();

        $this->post('/login', ['email' => 'admin@acme.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'admin@acme.test', 'password' => 'Secret@12345'])->assertRedirect();
        $this->assertAuthenticated();
        $this->assertDatabaseHas('login_histories', ['user_id' => $this->admin->id]);
    }
}
