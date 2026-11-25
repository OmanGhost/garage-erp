<?php

namespace Tests;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Plan;
use App\Modules\Core\Services\PermissionSync;
use App\Modules\Core\Services\TenantProvisioner;
use App\Modules\CRM\Models\Customer;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\Warehouse;
use App\Support\Money;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        PermissionSync::run();
        $this->seed(ReferenceDataSeeder::class);
    }

    protected function tearDown(): void
    {
        tenant()->forget();
        parent::tearDown();
    }

    /** Provision a tenant through the real provisioner and act inside it as its admin. */
    protected function makeTenant(string $slug = 'acme', string $email = 'admin@acme.test'): Company
    {
        $company = app(TenantProvisioner::class)->provision([
            'slug' => $slug, 'name' => 'Acme '.$slug, 'name_en' => 'Acme '.$slug,
            'plan_id' => Plan::query()->where('code', 'enterprise')->value('id'),
            'admin_name' => 'Admin', 'admin_email' => $email, 'admin_password' => 'Secret@12345',
        ]);
        $company->update(['subscription_status' => 'active', 'subscription_ends_at' => now()->addYear()]);

        $this->enterTenant($company, $email);

        return $company;
    }

    protected function enterTenant(Company $company, string $email): void
    {
        $this->company = $company;
        tenant()->set($company->id);
        $this->admin = User::query()->where('email', $email)->firstOrFail();
        tenant()->set($company->id, $company->branches()->value('id'));
        Auth::setUser($this->admin);
        $this->actingAs($this->admin);
    }

    /** Create a staff user in the current tenant with the given role and act as them. */
    protected function actAsRole(string $role): User
    {
        $u = new User(['name' => $role, 'email' => strtolower(str_replace(' ', '.', $role)).'@'.$this->company->slug.'.test', 'password' => 'Secret@12345']);
        $u->company_id = $this->company->id;
        $u->branch_id = $this->company->branches()->value('id');
        $u->is_active = true;
        $u->save();
        setPermissionsTeamId($this->company->id);
        $u->syncRoles([$role]);
        Auth::setUser($u);
        $this->actingAs($u);

        return $u;
    }

    protected function mainWarehouse(): Warehouse
    {
        return Warehouse::query()->where('code', 'WH-MAIN')->firstOrFail();
    }

    protected function cashAccountId(): int
    {
        return BankAccount::query()->where('type', 'cash')->orderBy('id')->value('id');
    }

    protected function makePart(array $attrs = []): Part
    {
        static $n = 0;
        $n++;

        return Part::create($attrs + [
            'sku' => 'P-'.$n, 'name_ar' => 'قطعة '.$n, 'name_en' => 'Part '.$n, 'part_type' => 'genuine',
            'purchase_price' => 10, 'sale_price' => 20, 'wholesale_price' => 18, 'workshop_price' => 20, 'tax_rate' => 5,
        ]);
    }

    protected function makeCustomer(array $attrs = []): Customer
    {
        static $n = 0;
        $n++;

        return Customer::create($attrs + ['code' => 'C-'.$n, 'name' => 'Customer '.$n, 'phone' => '+9689900000'.$n, 'type' => 'individual', 'credit_limit' => 1000]);
    }

    /** Net balance (debit - credit) of a system account. */
    protected function balance(string $systemKey): string
    {
        $id = Account::query()->where('system_key', $systemKey)->value('id');

        return Money::round(JournalLine::query()->where('account_id', $id)->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as b')->value('b') ?? 0);
    }

    protected function assertLedgerBalanced(): void
    {
        $d = JournalLine::query()->sum('debit');
        $c = JournalLine::query()->sum('credit');
        $this->assertSame(0, Money::cmp($d, $c), "Ledger not balanced: debit $d / credit $c");
    }

    protected function assertMoney(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertSame(0, Money::cmp($expected, $actual), $message ?: "Expected $expected, got $actual");
    }
}
