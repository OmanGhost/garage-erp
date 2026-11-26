<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Core\Models\Company;
use App\Support\AppVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * php artisan erp:doctor [--user=email]
 *
 * Checks the server against the requirements (PHP, extensions, limits, folders, database, drivers,
 * permissions) and, with --user, replays a vehicle reception as that user inside a transaction that is
 * rolled back (nothing is saved) and prints exactly why it is accepted or refused.
 */
class ErpDoctor extends Command
{
    protected $signature = 'erp:doctor {--user= : Email of a user to test (vehicle reception as that user)}';

    protected $description = 'Check server requirements and diagnose problems (optionally replay a vehicle reception as a user)';

    protected int $errors = 0;

    protected int $warnings = 0;

    public function handle(): int
    {
        $this->line('<options=bold>Garage ERP '.AppVersion::label().' — system check</>');

        $this->section('PHP');
        $this->check(version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP '.PHP_VERSION.' ('.PHP_SAPI.')', 'PHP 8.2 or newer is required');
        foreach (['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'gd', 'intl', 'json', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'zip'] as $ext) {
            $this->check(extension_loaded($ext), "extension $ext", "enable the PHP extension “{$ext}” (cPanel → Select PHP Version → Extensions)");
        }
        $mem = $this->bytes(ini_get('memory_limit'));
        $this->check($mem < 0 || $mem >= 256 * 1024 ** 2, 'memory_limit = '.ini_get('memory_limit'), 'set memory_limit to 256M or more', warn: true);
        $up = $this->bytes(ini_get('upload_max_filesize'));
        $post = $this->bytes(ini_get('post_max_size'));
        $this->check($up >= 8 * 1024 ** 2, 'upload_max_filesize = '.ini_get('upload_max_filesize'), 'set upload_max_filesize to 20M (photos of the vehicle)', warn: true);
        $this->check($post >= 20 * 1024 ** 2, 'post_max_size = '.ini_get('post_max_size'), 'set post_max_size to 50M', warn: true);
        $this->check((bool) ini_get('file_uploads'), 'file_uploads = On', 'enable file_uploads');
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        foreach (['proc_open' => 'scheduled backups (mysqldump) and "schedule:run"; use the separate cron lines', 'symlink' => 'php artisan storage:link (photos and logos)'] as $fn => $why) {
            $this->check(! in_array($fn, $disabled, true), "$fn() available", "$fn() is disabled on this server: affects $why", warn: true);
        }
        $this->line('  <fg=gray>note: the CLI and the website may use different PHP settings; the website values are in cPanel → Select PHP Version.</>');

        $this->section('Application');
        $this->check(filled(config('app.key')), 'APP_KEY is set', 'run php artisan key:generate');
        $this->check(! config('app.debug') || config('app.env') !== 'production', 'APP_DEBUG='.(config('app.debug') ? 'true' : 'false').' / APP_ENV='.config('app.env'), 'set APP_DEBUG=false in production', warn: true);
        $this->check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL = '.config('app.url'), 'APP_URL should be the https address of the site', warn: true);
        $secure = config('session.secure');
        $this->check(! $secure || str_starts_with((string) config('app.url'), 'https://'), 'SESSION_SECURE_COOKIE='.var_export($secure, true), 'secure cookies need https');
        foreach (['cache.default' => 'CACHE_STORE', 'session.driver' => 'SESSION_DRIVER', 'queue.default' => 'QUEUE_CONNECTION'] as $key => $env) {
            $v = (string) config($key);
            $this->check($v !== 'redis' || extension_loaded('redis') || class_exists(\Predis\Client::class) && config('database.redis.client') === 'predis',
                "$env = $v", "$env=redis but Redis is not available: use database");
        }
        $this->check(config('broadcasting.default') !== 'reverb' || $this->portOpen(), 'BROADCAST_CONNECTION = '.config('broadcasting.default'), 'on shared hosting use BROADCAST_CONNECTION=log', warn: true);
        $this->check(! file_exists(base_path('bootstrap/cache/config.php')) || filemtime(base_path('bootstrap/cache/config.php')) >= filemtime(base_path('.env')),
            'configuration cache is up to date', '.env changed after "php artisan optimize": run it again', warn: true);

        $this->section('Folders');
        foreach (['storage', 'storage/app', 'storage/framework/sessions', 'storage/framework/views', 'storage/framework/cache', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $this->check(is_dir(base_path($dir)) && is_writable(base_path($dir)), "$dir writable", "chmod -R 775 $dir");
        }
        $this->check(is_link(public_path('storage')) || is_dir(public_path('storage')), 'public/storage link', 'run php artisan storage:link', warn: true);

        $this->section('Database');
        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $version = $driver === 'sqlite' ? 'SQLite '.DB::selectOne('select sqlite_version() v')->v : DB::selectOne('select version() v')->v;
            $this->check(true, "connected ($driver $version, database ".DB::connection()->getDatabaseName().')', '');
            $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))->keys()
                ->diff(DB::table('migrations')->pluck('migration'));
            $this->check($pending->isEmpty(), 'migrations up to date', 'pending migrations ('.$pending->count().'): run php artisan erp:install');
            foreach (['users', 'companies', 'job_cards', 'idempotency_keys', 'sessions', 'cache', 'jobs'] as $t) {
                $this->check(Schema::hasTable($t), "table $t", "table $t is missing: run php artisan erp:install");
            }
            $perms = Permission::query()->count();
            $this->check($perms >= count(config('erp.permissions')), "permissions in database: $perms / ".count(config('erp.permissions')), 'run php artisan erp:install (permission sync)');
            $this->check(Role::query()->where('name', 'Company Admin')->exists(), 'role “Company Admin” exists', 'run php artisan erp:install');
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $queued = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
            $this->check($queued < 50, "queue: $queued waiting, $failed failed", 'jobs are waiting: add the queue:work cron line', warn: true);
        } catch (\Throwable $e) {
            $this->check(false, 'database connection', $e->getMessage());
        }

        if ($email = $this->option('user')) {
            $this->replayReception(strtolower($email));
        }

        $this->newLine();
        $summary = $this->errors ? "<fg=red>{$this->errors} problem(s)</>" : '<fg=green>no problems</>';
        $this->line("Result: $summary, {$this->warnings} warning(s).");

        return $this->errors ? self::FAILURE : self::SUCCESS;
    }

    /** Sign in as the user and send a real vehicle reception through the HTTP kernel, then roll everything back. */
    protected function replayReception(string $email): void
    {
        $this->section("Vehicle reception as $email");
        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $this->check(false, 'user', 'no user with this email');

            return;
        }
        $company = $user->company_id ? Company::query()->with('plan')->find($user->company_id) : null;
        $this->check((bool) $user->is_active, "user #{$user->id} {$user->name} is active", 'the user is disabled');
        $this->check((bool) $company, 'company: '.($company?->name ?? '—'), 'the user has no company');
        if (! $company) {
            return;
        }
        $this->check((bool) $company->is_active, 'company is active', 'the company is suspended');
        $this->check($company->subscriptionActive(), "subscription: {$company->subscription_status}, plan ".($company->plan?->code ?? '—')
            .($company->trial_ends_at ? ', trial ends '.$company->trial_ends_at->format('Y-m-d') : ''), 'the subscription has expired: the system is read-only');
        $this->check($company->moduleEnabled('workshop'), 'module “workshop” enabled', 'the workshop module is not enabled for this company / plan');

        tenant()->set($company->id);
        $roles = $user->getRoleNames()->implode(', ') ?: '—';
        $this->check($user->getRoleNames()->isNotEmpty(), "roles in this company: $roles", 'the user has no role in this company');
        $this->check($user->can('reception.create'), 'permission reception.create', 'the user cannot receive vehicles (give a role such as Receptionist or Company Admin)');
        tenant()->forget();

        DB::beginTransaction();
        try {
            $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
            $session = app('session')->driver();
            $session->start();
            $token = $session->token();
            $request = \Illuminate\Http\Request::create('/reception', 'POST', [
                '_token' => $token, '_idem' => 'doctor-'.uniqid(),
                'customer_name' => 'ERP Doctor Test', 'customer_phone' => '+96890000000', 'customer_type' => 'individual',
                'plate_number' => 'DR'.random_int(1000, 9999), 'plate_country' => 'OM', 'vehicle_type' => 'car',
                'odometer' => 1000, 'fuel_level' => 50, 'reported_issues' => 'System check (rolled back)', 'job_type' => 'repair', 'priority' => 'normal',
                'expected_delivery_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ], [], [], ['HTTPS' => str_starts_with((string) config('app.url'), 'https') ? 'on' : null, 'HTTP_HOST' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
                'HTTP_REFERER' => url('/reception')]);
            $request->setLaravelSession($session);
            $request->headers->set('X-CSRF-TOKEN', $token);
            app('auth')->guard('web')->setUser($user);
            app()->instance('request', $request);
            $response = $kernel->handle($request);
            $status = $response->getStatusCode();
            $location = $response->headers->get('Location');
            $sessionError = $session->get('error');
            $errors = $session->get('errors')?->all() ?? [];
            $exception = $response->exception ?? null;

            if ($status === 302 && $location && str_contains($location, '/job-cards/')) {
                $this->check(true, 'reception accepted → '.$location.' (rolled back, nothing saved)', '');
            } else {
                $detail = trim(implode(' | ', array_filter([
                    "HTTP $status".($location ? " → $location" : ''),
                    $sessionError, implode('; ', $errors),
                    $exception ? get_class($exception).': '.$exception->getMessage().' @ '.str_replace(base_path().'/', '', $exception->getFile()).':'.$exception->getLine() : null,
                ])));
                $this->check(false, 'reception', 'refused: '.$detail);
            }
        } catch (\Throwable $e) {
            $this->check(false, 'reception', get_class($e).': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
        } finally {
            DB::rollBack();
            tenant()->forget();
        }
    }

    // ------------------------------------------------------------------ helpers

    protected function section(string $title): void
    {
        $this->newLine();
        $this->line("<options=bold>$title</>");
    }

    protected function check(bool $ok, string $label, string $fix, bool $warn = false): void
    {
        if ($ok) {
            $this->line("  <fg=green>✔</> $label");

            return;
        }
        if ($warn) {
            $this->warnings++;
            $this->line("  <fg=yellow>!</> $label — <fg=yellow>$fix</>");

            return;
        }
        $this->errors++;
        $this->line("  <fg=red>✘</> $label — <fg=red>$fix</>");
    }

    protected function bytes(string|false $v): int
    {
        $v = trim((string) $v);
        if ($v === '' || $v === '-1') {
            return -1;
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
        };
    }

    protected function portOpen(): bool
    {
        $c = @fsockopen((string) config('reverb.servers.reverb.host', '127.0.0.1'), (int) config('reverb.servers.reverb.port', 8080), $errno, $err, 1);
        if ($c) {
            fclose($c);

            return true;
        }

        return false;
    }
}
