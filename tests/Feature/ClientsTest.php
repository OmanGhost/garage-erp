<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Plan;
use App\Modules\Core\Models\Subscription;
use App\Modules\Core\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ClientsTest extends TestCase
{
    protected User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant('alpha', 'admin@alpha.test');
        $this->company->update(['contact_name' => 'Salim', 'cr_number' => '7788']);
        tenant()->forget();
        Auth::forgetUser();
        $this->super = new User(['name' => 'Platform', 'email' => 'super@platform.test', 'password' => 'Secret@12345']);
        $this->super->is_super_admin = true;
        $this->super->is_active = true;
        $this->super->save();
        $this->actingAs($this->super);
    }

    public function test_list_search_filters_and_export(): void
    {
        $this->get('/admin/clients')->assertOk()->assertSee('Acme alpha')->assertSee('Salim');
        $this->get('/admin/clients?q=7788')->assertOk()->assertSee('Acme alpha');
        $this->get('/admin/clients?q=nothing-matches')->assertOk()->assertDontSee('Acme alpha');
        foreach (\App\Modules\Saas\Http\Controllers\ClientController::FILTERS as $f) {
            $this->get('/admin/clients?filter='.$f.'&sort=ends')->assertOk();
        }
        $this->get('/admin/clients?export=csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_client_file_tabs_render(): void
    {
        foreach (['overview', 'subscription', 'users', 'invoices', 'activity'] as $tab) {
            $this->get('/admin/clients/'.$this->company->id.'?tab='.$tab)->assertOk()->assertSee('Acme alpha');
        }
        // old URL still works
        $this->get('/admin/companies/'.$this->company->id)->assertRedirect(route('admin.clients.show', $this->company->id));
    }

    public function test_profile_update_and_suspension_blocks_sign_in(): void
    {
        $this->put('/admin/clients/'.$this->company->id.'/profile', ['name' => 'شركة ألفا', 'name_en' => 'Alpha Co', 'contact_phone' => '91234567', 'admin_notes' => 'VIP'])
            ->assertSessionHasNoErrors();
        $c = $this->company->fresh();
        $this->assertSame('Alpha Co', $c->name_en);
        $this->assertSame('+96891234567', $c->contact_phone);

        $this->post('/admin/clients/'.$c->id.'/toggle');
        $this->assertFalse($c->fresh()->is_active);
        Auth::logout();
        $this->post('/login', ['email' => 'admin@alpha.test', 'password' => 'Secret@12345']);
        $this->get('/dashboard')->assertRedirect(); // suspended company: signed out
        $this->assertGuest();

        $this->actingAs($this->super)->post('/admin/clients/'.$c->id.'/toggle');
        $this->assertTrue($c->fresh()->is_active);
    }

    public function test_extend_trial_reset_password_and_disable_user(): void
    {
        $c = $this->company;
        $this->post('/admin/clients/'.$c->id.'/extend-trial', ['days' => 14])->assertSessionHas('success');
        $this->assertTrue($c->fresh()->trial_ends_at->isAfter(now()->addDays(13)));

        $admin = User::query()->where('email', 'admin@alpha.test')->first();
        $old = $admin->password;
        $res = $this->post('/admin/clients/'.$c->id.'/users/'.$admin->id.'/reset-password')->assertSessionHas('success');
        $this->assertNotSame($old, $admin->fresh()->password);
        $this->assertStringContainsString('admin@alpha.test', session('success'));

        $this->post('/admin/clients/'.$c->id.'/users/'.$admin->id.'/toggle');
        $this->assertFalse((bool) $admin->fresh()->is_active);

        // a user of another client cannot be touched through this client
        $this->post('/admin/clients/'.$c->id.'/users/'.$this->super->id.'/reset-password')->assertNotFound();
    }

    public function test_mark_subscription_invoice_paid(): void
    {
        $plan = Plan::query()->first();
        $sub = Subscription::create(['company_id' => $this->company->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'status' => 'active',
            'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(), 'amount' => 25]);
        $inv = SubscriptionInvoice::create(['company_id' => $this->company->id, 'subscription_id' => $sub->id, 'number' => 'SUB-T-1',
            'period_start' => now()->toDateString(), 'period_end' => now()->addMonth()->toDateString(), 'amount' => 25, 'status' => 'unpaid']);

        $this->get('/admin/clients/'.$this->company->id.'?tab=invoices')->assertSee('SUB-T-1');
        $this->post('/admin/clients/'.$this->company->id.'/invoices/'.$inv->id.'/paid')->assertSessionHas('success');
        $this->assertSame('paid', $inv->fresh()->status);
    }

    public function test_archive_requires_confirmation_and_can_be_restored(): void
    {
        $c = $this->company;
        $this->delete('/admin/clients/'.$c->id, ['confirm_slug' => 'wrong'])->assertSessionHasErrors('confirm_slug');
        $this->assertNotNull(Company::query()->find($c->id));

        $this->delete('/admin/clients/'.$c->id, ['confirm_slug' => 'alpha'])->assertRedirect(route('admin.clients.index'));
        $this->assertNull(Company::query()->find($c->id));
        $this->get('/admin/clients?filter=archived')->assertOk()->assertSee('Acme alpha');
        $this->get('/admin/clients/'.$c->id)->assertOk(); // archived file still viewable

        $this->post('/admin/clients/'.$c->id.'/restore')->assertRedirect(route('admin.clients.show', $c->id));
        $this->assertTrue(Company::query()->findOrFail($c->id)->is_active);
    }

    public function test_only_super_admin_can_manage_clients(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@alpha.test')->first());
        $this->get('/admin/clients')->assertForbidden();
        // refused: back to the page with the reason, and the company is not suspended
        $this->post('/admin/clients/'.$this->company->id.'/toggle')->assertRedirect()->assertSessionHas('error');
        $this->assertTrue((bool) $this->company->fresh()->is_active);
    }
}
