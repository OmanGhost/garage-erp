<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Core\Services\PermissionSync;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan migrate --seed   -> permissions, roles, reference data,
     * super admin (superadmin@garage-erp.test / ChangeMe@2026) and demo company.
     */
    public function run(): void
    {
        PermissionSync::run();
        $this->call(ReferenceDataSeeder::class);

        $admin = User::query()->firstOrNew(['email' => 'superadmin@garage-erp.test']);
        $admin->name = 'Super Admin';
        $admin->password = 'ChangeMe@2026';
        $admin->is_super_admin = true;
        $admin->locale = 'ar';
        $admin->save();

        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
