<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Integration;
use App\Modules\Core\Models\Plan;
use App\Modules\Saas\Models\DomainRequest;
use App\Modules\Saas\Services\DomainService;
use App\Support\Mail\MailService;
use Tests\TestCase;

class DomainsAndMailTest extends TestCase
{
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = $this->makeTenant();
        $this->app->setLocale('ar');
    }

    protected function superAdmin(): User
    {
        $u = new User(['name' => 'Platform', 'email' => 'root@platform.test', 'password' => 'Secret@12345']);
        $u->is_super_admin = true;
        $u->is_active = true;
        $u->save();

        return $u;
    }

    public function test_company_smtp_settings_are_saved_encrypted_and_password_kept(): void
    {
        $this->get('/settings')->assertOk()->assertSee('id="smtp"', false)->assertSee(__('SMTP server'));
        $this->post('/settings/smtp', ['host' => 'mail.acme.om', 'port' => 465, 'encryption' => 'ssl', 'username' => 'no-reply@acme.om',
            'password' => 'MailPass#1', 'from_address' => 'no-reply@acme.om', 'from_name' => 'Acme Garage', 'is_active' => '1'])->assertSessionHas('success');

        $i = tenant()->run($this->company->id, fn () => Integration::query()->where('provider', 'smtp')->firstOrFail());
        $this->assertSame('MailPass#1', $i->credentials['password']);
        $raw = \Illuminate\Support\Facades\DB::table('integrations')->where('id', $i->id)->value('credentials');
        $this->assertStringNotContainsString('MailPass', (string) $raw); // stored encrypted

        // leaving the password empty keeps it
        $this->post('/settings/smtp', ['host' => 'mail2.acme.om', 'port' => 587, 'encryption' => 'tls', 'from_address' => 'no-reply@acme.om', 'is_active' => '1']);
        $i->refresh();
        $this->assertSame('mail2.acme.om', $i->credentials['host']);
        $this->assertSame('MailPass#1', $i->credentials['password']);
        $this->assertTrue(tenant()->run($this->company->id, fn () => MailService::available()));

        // invalid host / from address are refused
        $this->post('/settings/smtp', ['host' => 'bad host!', 'port' => 465, 'encryption' => 'ssl', 'from_address' => 'nope'])->assertSessionHasErrors(['host', 'from_address']);
        // a test against an unreachable server reports the error instead of crashing
        $this->post('/settings/smtp/test', ['test_to' => 'me@acme.om'])->assertSessionHas('error');
    }

    public function test_super_admin_manages_platform_smtp(): void
    {
        $this->actingAs($this->superAdmin());
        $this->get('/admin/domains')->assertOk()->assertSee(__('Platform e-mail (SMTP)'));
        $this->post('/admin/domains/smtp', ['host' => 'mail.platform.om', 'port' => 465, 'encryption' => 'ssl', 'from_address' => 'noreply@platform.om', 'password' => 'X1'])
            ->assertSessionHas('success');
        $this->assertSame('mail.platform.om', MailService::platformConfig()['host']);
    }

    public function test_plan_option_and_client_domain_request_flow(): void
    {
        $plan = $this->company->plan;
        $plan->update(['allow_custom_domain' => false, 'domain_fee' => 15]);

        // not in plan: no form, request refused
        $this->get('/billing')->assertOk()->assertSee(__('Not included in your plan'));
        $this->post('/domain-requests', ['type' => 'register', 'domain' => 'acme-garage.om'])->assertSessionHas('error');

        $plan->update(['allow_custom_domain' => true]);
        $this->get('/billing')->assertOk()->assertSee(__('Included in your plan'))->assertSee(__('Register a new domain'));
        $this->post('/domain-requests', ['type' => 'register', 'domain' => 'bad domain'])->assertSessionHasErrors('domain');
        $this->post('/domain-requests', ['type' => 'register', 'domain' => 'HTTPS://Acme-Garage.OM/', 'years' => 2, 'notes' => 'please'])->assertSessionHas('success');
        $r = DomainRequest::query()->firstOrFail();
        $this->assertSame('acme-garage.om', $r->domain);
        $this->assertEquals(15, (float) $r->price);
        $this->post('/domain-requests', ['type' => 'connect', 'domain' => 'other.om'])->assertSessionHas('warning'); // one open request at a time

        // platform admin completes it -> the domain is assigned (pending DNS)
        $this->actingAs($this->superAdmin());
        $this->get('/admin/domains')->assertOk()->assertSee('acme-garage.om');
        $this->post('/admin/domains/requests/'.$r->id, ['status' => 'completed', 'admin_notes' => 'Registered'])->assertSessionHas('success');
        $c = $this->company->fresh();
        $this->assertSame('acme-garage.om', $c->custom_domain);
        $this->assertSame('pending', $c->domain_status);
        $this->assertNotNull($c->domain_token);

        // DNS check: TXT token found -> active
        $svc = app(DomainService::class);
        $res = $svc->verify($c, fn ($name, $type) => $type === DNS_TXT ? [['txt' => 'garage-erp-verify='.$c->domain_token]] : []);
        $this->assertTrue($res['ok']);
        $this->assertSame('active', $c->fresh()->domain_status);
    }

    public function test_plans_page_saves_the_custom_domain_option(): void
    {
        $this->actingAs($this->superAdmin());
        $plan = Plan::query()->where('code', 'starter')->firstOrFail();
        $this->get('/admin/plans')->assertOk()->assertSee('allow_custom_domain', false);
        $this->post('/admin/plans', ['id' => $plan->id, 'code' => $plan->code, 'name' => $plan->name, 'price_monthly' => 10, 'price_yearly' => 100, 'trial_days' => 14,
            'modules' => $plan->modules, 'is_active' => '1', 'allow_custom_domain' => '1', 'domain_fee' => '12.5'])->assertRedirect();
        $plan->refresh();
        $this->assertTrue((bool) $plan->allow_custom_domain);
        $this->assertEquals(12.5, (float) $plan->domain_fee);
    }

    public function test_custom_domain_shows_company_login_and_blocks_other_accounts(): void
    {
        $this->company->forceFill(['custom_domain' => 'erp.acme.om', 'domain_status' => 'active'])->save();
        auth()->logout();

        $this->get('http://erp.acme.om/')->assertRedirect('/login');
        $this->get('http://erp.acme.om/login')->assertOk()->assertSee($this->company->displayName());

        // a user of another company is signed out on this domain
        $other = $this->makeTenant('beta', 'admin@beta.test');
        $betaAdmin = User::query()->where('email', 'admin@beta.test')->first();
        $this->actingAs($betaAdmin)->get('http://erp.acme.om/dashboard')->assertRedirect('/login');
        $this->assertGuest();

        // the platform host is unaffected
        $this->get('http://localhost/')->assertOk();
    }
}
