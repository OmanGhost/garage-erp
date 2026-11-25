<?php

/**
 * Manual smoke test: php tests/smoke.php [email]
 * Logs in as a user of the seeded demo database and requests every GET route
 * (filling route parameters with the first matching record). Prints non-2xx/3xx results.
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$email = $argv[1] ?? 'admin@alnoor-auto.om';
$user = App\Models\User::query()->where('email', $email)->firstOrFail();
$GLOBALS['cid'] = $user->company_id;

$skip = ['logout', 'sanctum', 'livewire', 'storage', 'pay/', 'api/', 'up', 'export', '_ignition', 'backups/download', 'attachments'];
$params = [
    'slug' => fn () => null,
];
$bindings = [];
$tables = [
    'customer' => 'customers', 'vehicle' => 'vehicles', 'jobCard' => 'job_cards', 'job' => 'job_cards', 'quotation' => 'quotations',
    'inspection' => 'inspections', 'invoice' => 'invoices', 'payment' => 'payments', 'part' => 'parts', 'supplier' => 'suppliers',
    'purchaseOrder' => 'purchase_orders', 'order' => 'purchase_orders', 'receipt' => 'goods_receipts', 'employee' => 'employees', 'payroll' => 'payrolls',
    'account' => 'accounts', 'entry' => 'journal_entries', 'journalEntry' => 'journal_entries', 'session' => 'pos_sessions', 'transfer' => 'stock_transfers',
    'count' => 'stock_counts', 'adjustment' => 'stock_adjustments', 'vehicleDismantle' => 'dismantled_vehicles', 'dismantled' => 'dismantled_vehicles',
    'claim' => 'insurance_claims', 'warranty' => 'warranties', 'appointment' => 'appointments', 'company' => 'companies', 'role' => 'roles',
    'user' => 'users', 'creditNote' => 'credit_notes', 'fleet' => 'fleets', 'asset' => 'fixed_assets', 'equipment' => 'equipment', 'make' => 'vehicle_makes',
    'storeOrder' => 'store_orders', 'salesOrder' => 'sales_orders', 'reconciliation' => 'bank_reconciliations', 'expense' => 'expenses', 'report' => null,
];

$fail = 0; $ok = 0;
foreach (Route::getRoutes() as $route) {
    if (! in_array('GET', $route->methods(), true)) continue;
    $uri = $route->uri();
    foreach ($skip as $s) if (str_starts_with($uri, $s) || str_contains($uri, $s)) continue 2;
    $mw = $route->gatherMiddleware();
    $isPublic = ! in_array('auth', $mw, true) && ! collect($mw)->contains(fn ($m) => str_starts_with($m, 'auth'));
    $url = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($tables, $uri) {
        $n = $m[1];
        if ($n === 'slug' && str_starts_with($uri, 'm/')) return '__SLUG__';
        if ($n === 'slug' || $n === 'company') return 'alnoor-auto';
        if ($n === 'report') return 'trial-balance';
        if ($n === 'token') return 'x';
        $t = $tables[$n] ?? null;
        if (! $t) return '__UNKNOWN_'.$n;
        $id = DB::table($t)->value('id');
        return $id ?: '__NONE_'.$n;
    }, $uri);
    $urls = [$url];
    if (str_contains($url, '__SLUG__')) {
        $urls = [];
        foreach (App\Support\Crud\CrudRegistry::all() as $slug => $cls) {
            $u = str_replace('__SLUG__', $slug, $url);
            if (str_contains($u, '{id}') || str_contains($u, '/{record}')) {
                $model = $cls::$model;
                $id = $model::query()->withoutGlobalScopes()->where(function ($q) use ($model) {
                    if (in_array('company_id', \Schema::getColumnListing((new $model)->getTable()))) $q->where('company_id', $GLOBALS['cid']);
                })->value('id');
                if (! $id) continue;
                $u = preg_replace('/\{\w+\}/', $id, $u);
            }
            $urls[] = $u;
        }
    }
    foreach ($urls as $u) {
        if (str_contains($u, '__')) { echo "SKIP $u\n"; continue; }
        $app['auth']->forgetGuards();
        $req = Request::create('/'.ltrim($u, '/'), 'GET');
        $req->setLaravelSession($s = $app['session']->driver());
        $s->start();
        if (! $isPublic) {
            Auth::guard('web')->login($user);
        }
        try {
            $res = $kernel->handle($req);
            $code = $res->getStatusCode();
        } catch (Throwable $e) {
            $code = 'EX '.$e->getMessage();
        }
        if (is_int($code) && $code < 400) { $ok++; if (getenv("SHOWOK")) echo "$code /$u
"; } else {
            $fail++;
            $msg = '';
            if (isset($res) && is_int($code) && $code >= 500) {
                $msg = $res->exception?->getMessage().' @ '.basename((string) $res->exception?->getFile()).':'.$res->exception?->getLine();
            }
            echo "$code /$u $msg\n";
        }
        $kernel->terminate($req, $res ?? null);
        tenant()->forget();
    }
}
echo "OK $ok  FAIL $fail\n";
