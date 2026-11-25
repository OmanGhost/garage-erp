<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Core\Services\PermissionSync;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * php artisan erp:install --admin=admin@example.com [--password=...] [--demo]
 * Runs migrations, syncs permissions & roles, loads reference data and creates
 * the SaaS super administrator. Safe to re-run (idempotent).
 */
class ErpInstall extends Command
{
    protected $signature = 'erp:install {--admin= : Super admin email} {--password= : Super admin password} {--demo : Load the demo company}';

    protected $description = 'Install / update the Garage ERP (migrations, permissions, reference data, super admin)';

    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);
        PermissionSync::run();
        $this->info('Permissions & roles synchronized ('.count(config('erp.permissions')).' permissions).');
        $this->call('db:seed', ['--class' => ReferenceDataSeeder::class, '--force' => true]);

        if ($email = $this->option('admin')) {
            $password = $this->option('password') ?: Str::password(16);
            $user = User::query()->where('email', $email)->first() ?? new User(['name' => 'Super Admin', 'email' => strtolower($email)]);
            $user->password = $password;
            $user->is_super_admin = true;
            $user->is_active = true;
            $user->save();
            $this->info("Super admin: {$email}".($this->option('password') ? '' : " / password: {$password}"));
        }
        if ($this->option('demo')) {
            $this->call('db:seed', ['--class' => \Database\Seeders\DemoSeeder::class, '--force' => true]);
        }
        $this->call('storage:link');

        return self::SUCCESS;
    }
}
