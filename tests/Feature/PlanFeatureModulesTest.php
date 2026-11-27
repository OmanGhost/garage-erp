<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Plan;
use App\Modules\Saas\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PlanFeatureModulesTest extends TestCase
{
    public function test_new_features_follow_the_plan_modules(): void
    {
        $company = $this->makeTenant();
        // a plan with the workshop and inventory but none of the new features
        $basic = Plan::query()->create(['code' => 'basic-x', 'name' => 'Basic X', 'price_monthly' => 10, 'price_yearly' => 100, 'trial_days' => 0,
            'modules' => ['core', 'crm', 'vehicles', 'workshop', 'inventory', 'sales'], 'is_active' => true]);
        $company->update(['plan_id' => $basic->id, 'enabled_modules' => null]);
        tenant()->forget();
        tenant()->set($company->id, $company->branches()->value('id'));

        foreach (['/workshop/planner', '/workshop/tracking', '/quality', '/inventory/analytics', '/inventory/reservations', '/inventory/consignment', '/m/nonconformities'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->get('/dashboard')->assertOk()->assertDontSee(route('workshop.planner'), false)->assertDontSee(route('quality.dashboard'), false);
        $this->get('/inventory/cross-reference')->assertOk();               // part of the inventory module

        // switching the features on in the client file opens them
        $company->update(['enabled_modules' => AdminController::normalizeModules(['workshop', 'inventory', 'sales', 'planning', 'quality', 'inventory_analytics'])]);
        tenant()->forget();
        tenant()->set($company->id, $company->branches()->value('id'));
        $this->get('/workshop/planner')->assertOk();
        $this->get('/quality')->assertOk();
        $this->get('/inventory/analytics')->assertOk();
        $this->get('/workshop/tracking')->assertForbidden();
    }

    public function test_plan_editor_lists_features_and_drops_orphans(): void
    {
        $this->assertSame(['core', 'crm', 'vehicles'], array_values(array_intersect(AdminController::normalizeModules(['quality']), ['core', 'crm', 'vehicles', 'quality'])));
        $this->assertNotContains('quality', AdminController::normalizeModules(['quality']));              // needs the workshop
        $this->assertContains('quality', AdminController::normalizeModules(['workshop', 'quality']));

        tenant()->forget();
        Auth::forgetUser();
        $super = new User(['name' => 'Platform', 'email' => 'super@platform.test', 'password' => 'Secret@12345']);
        $super->is_super_admin = true;
        $super->is_active = true;
        $super->save();
        $this->actingAs($super);
        $this->get('/admin/plans')->assertOk()->assertSee(__('Feature comparison'))->assertSee(__('Quality management (QMS)'))->assertSee('module-option', false);
        $this->get('/admin')->assertOk()->assertSee(__('New features — clients with access'));

        $plan = Plan::query()->orderBy('sort')->firstOrFail();
        $this->post('/admin/plans', ['id' => $plan->id, 'code' => $plan->code, 'name' => $plan->name, 'price_monthly' => 1, 'price_yearly' => 10, 'trial_days' => 0,
            'modules' => ['sales', 'dvi', 'inventory', 'inventory_ops']])->assertSessionHas('success');
        $mods = $plan->fresh()->modules;
        $this->assertContains('inventory_ops', $mods);
        $this->assertNotContains('dvi', $mods);                // workshop not selected
        $this->assertContains('core', $mods);
    }
}
