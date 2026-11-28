<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\RoleChangeRequest;
use Tests\TestCase;

class RoleChangeRequestTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
        $this->admin = auth()->user();
    }

    protected function roleOf(User $u): ?string
    {
        setPermissionsTeamId(tenant()->id());

        return $u->fresh()->roles()->pluck('name')->first();
    }

    public function test_editing_a_user_works_and_the_form_shows_the_current_role(): void
    {
        $u = $this->actAsRole('Accountant');
        $this->actingAs($this->admin);
        $this->get('/m/users/'.$u->id.'/edit')->assertOk()->assertSee('value="Accountant" selected', false);

        // used to fail with a server error (password rule object) — any edit of a user
        $this->put('/m/users/'.$u->id, ['name' => 'Renamed', 'email' => $u->email, 'role' => 'Accountant', 'locale' => 'ar', 'is_active' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Renamed', $u->fresh()->name);

        // a company administrator may still change the role directly (logged)
        $this->put('/m/users/'.$u->id, ['name' => 'Renamed', 'email' => $u->email, 'role' => 'Cashier', 'locale' => 'ar', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Cashier', $this->roleOf($u));
    }

    public function test_request_approved_by_a_branch_manager_and_printed(): void
    {
        $target = $this->actAsRole('Accountant');
        $manager = $this->actAsRole('Branch Manager');
        $hr = $this->actAsRole('HR Manager');

        // the HR manager may request but not change the role directly
        $this->put('/m/users/'.$target->id, ['name' => $target->name, 'email' => $target->email, 'role' => 'Cashier', 'locale' => 'ar', 'is_active' => 1]);
        $this->assertSame('Accountant', $this->roleOf($target));

        $this->get('/role-requests/create?user='.$target->id)->assertOk();
        $this->post('/role-requests', ['user_id' => $target->id, 'requested_role' => 'Cashier', 'reason' => 'short'])->assertSessionHasErrors('reason');
        $this->post('/role-requests', ['user_id' => $target->id, 'requested_role' => 'Cashier', 'reason' => 'Moved to the cash counter of the main branch'])->assertRedirect();
        $r = RoleChangeRequest::query()->firstOrFail();
        $this->assertSame(['pending', 'Accountant', 'Cashier'], [$r->status, $r->current_role, $r->requested_role]);
        $this->assertSame('Accountant', $this->roleOf($target));                    // nothing changes before approval
        $this->assertSame(1, $manager->notifications()->count());

        // a second request for the same user is refused; the requester cannot decide
        $this->post('/role-requests', ['user_id' => $target->id, 'requested_role' => 'Storekeeper', 'reason' => 'Another reason for the change'])->assertSessionHas('error');
        $this->post('/role-requests/'.$r->id.'/decide', ['decision' => 'approve'])->assertSessionHas('error');

        $this->actingAs($manager);
        $this->get('/role-requests/'.$r->id)->assertOk()->assertSee(__('Save decision'));
        $this->post('/role-requests/'.$r->id.'/decide', ['decision' => 'approve', 'decision_notes' => 'OK from the branch'])->assertSessionHas('success');
        $r->refresh();
        $this->assertSame('approved', $r->status);
        $this->assertSame($manager->id, (int) $r->decided_by);
        $this->assertSame('Cashier', $this->roleOf($target));
        $this->assertSame(1, $hr->notifications()->count());
        $this->get('/role-requests/'.$r->id.'/print')->assertOk()->assertSee('APPROVED')->assertSee('Moved to the cash counter of the main branch');
        $this->get('/role-requests')->assertOk()->assertSee($r->number);
    }

    public function test_rejection_needs_a_reason_and_sensitive_roles_need_a_company_admin(): void
    {
        $target = $this->actAsRole('Cashier');
        $manager = $this->actAsRole('Branch Manager');
        $this->actingAs($this->admin);

        $this->post('/role-requests', ['user_id' => $target->id, 'requested_role' => 'General Manager', 'reason' => 'Promotion to general manager'])->assertRedirect();
        $r = RoleChangeRequest::query()->firstOrFail();

        $this->actingAs($manager);
        $this->post('/role-requests/'.$r->id.'/decide', ['decision' => 'approve'])->assertSessionHas('error');   // only a company admin grants it
        $this->post('/role-requests/'.$r->id.'/decide', ['decision' => 'reject'])->assertSessionHas('error');    // the admin requested; the manager may not either way
        $this->assertSame('pending', $r->fresh()->status);

        // a normal request rejected with notes
        $r->update(['status' => 'cancelled']);
        $this->actingAs($this->admin);
        $this->post('/role-requests', ['user_id' => $target->id, 'requested_role' => 'Storekeeper', 'reason' => 'Needed in the parts store'])->assertRedirect();
        $r2 = RoleChangeRequest::query()->where('status', 'pending')->firstOrFail();
        $this->actingAs($manager);
        $this->post('/role-requests/'.$r2->id.'/decide', ['decision' => 'reject', 'decision_notes' => ''])->assertSessionHas('error');
        $this->post('/role-requests/'.$r2->id.'/decide', ['decision' => 'reject', 'decision_notes' => 'Not enough staff at the counter'])->assertSessionHas('success');
        $this->assertSame('rejected', $r2->fresh()->status);
        $this->assertSame('Cashier', $this->roleOf($target));
        $this->get('/role-requests/'.$r2->id.'/print')->assertOk()->assertSee('REJECTED')->assertSee('Not enough staff at the counter');

        // a cashier cannot see the requests
        $this->actingAs($target);
        $this->get('/role-requests')->assertForbidden();
    }
}
