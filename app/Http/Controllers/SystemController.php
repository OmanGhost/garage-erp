<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Integration;
use App\Modules\Core\Services\FileStorage;
use App\Modules\Core\Services\IntegrationManager;
use App\Modules\Core\Services\PermissionSync;
use App\Modules\Inventory\Models\Warehouse;
use App\Support\Audit\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class SystemController extends Controller
{
    public function switchLocale(Request $request)
    {
        $locale = in_array($request->input('locale'), ['ar', 'en'], true) ? $request->input('locale') : 'ar';
        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->saveQuietly();

        return back();
    }

    public function switchBranch(Request $request)
    {
        $id = (int) $request->input('branch_id');
        $allowed = $request->user()->allowedBranchIds();
        abort_unless(Branch::query()->whereKey($id)->exists() && ($allowed === null || in_array($id, $allowed, true)), 403);
        $request->session()->put('branch_id', $id);

        return back()->with('success', __('Branch switched.'));
    }

    public function manifest()
    {
        $c = auth()->check() ? tenant()->company() : null;

        return response()->json([
            'name' => $c?->displayName() ?? config('app.name'),
            'short_name' => 'Garage ERP',
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#0f1b2d',
            'theme_color' => $c?->primary_color ?? '#1f6feb',
            'dir' => is_rtl() ? 'rtl' : 'ltr',
            'lang' => app()->getLocale(),
            'icons' => [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    // ------------------------------------------------------------- settings

    public function settings()
    {
        abort_unless(auth()->user()->can('settings.view'), 403);

        $smtp = \App\Modules\Core\Models\Integration::query()->where('provider', 'smtp')->first();

        return view('system.settings', ['company' => tenant()->company(), 'defaults' => config('erp.defaults'),
            'smtp' => $smtp?->credentials ?? [], 'smtpActive' => (bool) ($smtp?->is_active ?? true), 'platformSmtp' => (bool) \App\Support\Mail\MailService::platformConfig()]);
    }

    public function updateSettings(Request $request)
    {
        abort_unless($request->user()->can('settings.update'), 403);
        $company = tenant()->company();
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'], 'name_en' => ['nullable', 'string', 'max:190'],
            'legal_name' => ['nullable', 'string', 'max:190'], 'cr_number' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'], 'city' => ['nullable', 'string', 'max:100'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'locale' => ['required', 'in:ar,en'], 'logo' => ['nullable', 'image', 'max:2048'],
            'settings' => ['array'],
        ]);
        if ($request->hasFile('logo')) {
            $company->logo_path = FileStorage::storePublicImage($request->file('logo'), 'logos');
        }
        $company->fill(collect($data)->except(['logo', 'settings'])->all());
        $company->name = $data['name_ar'];
        $company->phone = omani_phone($data['phone'] ?? null);
        $settings = $company->settings ?? [];
        foreach (config('erp.defaults') as $key => $default) {
            $field = str_replace('.', '__', $key);
            if (is_bool($default)) {
                $settings[$key] = (bool) ($data['settings'][$field] ?? false);
            } elseif (array_key_exists($field, $data['settings'] ?? [])) {
                $settings[$key] = $data['settings'][$field];
            }
        }
        if (! in_array($settings['inventory.valuation_method'] ?? 'weighted_average', ['weighted_average', 'fifo'], true)) {
            $settings['inventory.valuation_method'] = 'weighted_average';
        }
        $company->settings = $settings;
        $company->save();
        AuditLogger::log('settings.updated', $company);

        return back()->with('success', __('Settings saved.'));
    }

    // ---------------------------------------------------------------- roles

    public function roles()
    {
        abort_unless(auth()->user()->can('roles.view'), 403);
        $roles = Role::query()->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', tenant()->id()))->withCount('permissions')->orderBy('id')->get();

        return view('system.roles', compact('roles'));
    }

    public function editRole(Role $role)
    {
        abort_unless(auth()->user()->can('roles.view'), 403);
        abort_unless($role->company_id === null || $role->company_id === tenant()->id(), 404);
        $grouped = collect(config('erp.permissions'))->groupBy(fn ($p) => explode('.', $p)[0]);

        return view('system.role-edit', ['role' => $role, 'grouped' => $grouped, 'has' => $role->permissions->pluck('name')->all()]);
    }

    public function storeRole(Request $request)
    {
        abort_unless($request->user()->can('roles.create'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->where(fn ($q) => $q->where('company_id', tenant()->id())->orWhereNull('company_id'))]]);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web', 'company_id' => tenant()->id()]);

        return redirect()->route('roles.edit', $role)->with('success', __('Role created. Select its permissions.'));
    }

    public function updateRole(Request $request, Role $role)
    {
        abort_unless($request->user()->can('roles.update'), 403);
        abort_unless($role->company_id === null || $role->company_id === tenant()->id(), 404);
        abort_if(in_array($role->name, ['Super Admin', 'Company Admin'], true), 403, __('This role cannot be modified.'));
        if ($role->company_id === null) {
            // global templates are copied per company on first change
            $copy = Role::query()->where('company_id', tenant()->id())->where('name', $role->name.' ('.tenant()->company()->name.')')->first()
                ?? Role::create(['name' => $role->name.' ('.tenant()->company()->name.')', 'guard_name' => 'web', 'company_id' => tenant()->id()]);
            $role = $copy;
        }
        $perms = array_values(array_intersect((array) $request->input('permissions', []), config('erp.permissions')));
        $role->syncPermissions($perms);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        AuditLogger::log('role.permissions_updated', null, ['role' => $role->name, 'count' => count($perms)]);

        return redirect()->route('roles.edit', $role)->with('success', __('Permissions saved.'));
    }

    public function userAccess(User $user)
    {
        abort_unless(auth()->user()->can('users.update') && $user->company_id === tenant()->id(), 403);

        return view('system.user-access', [
            'user' => $user, 'branches' => Branch::query()->get(), 'warehouses' => Warehouse::query()->get(),
            'departments' => \App\Modules\Workshop\Models\Department::query()->get(),
        ]);
    }

    public function saveUserAccess(Request $request, User $user)
    {
        abort_unless($request->user()->can('users.update') && $user->company_id === tenant()->id(), 403);
        $branchIds = Branch::query()->whereIn('id', (array) $request->input('branches', []))->pluck('id');
        $whIds = Warehouse::query()->whereIn('id', (array) $request->input('warehouses', []))->pluck('id');
        $depIds = \App\Modules\Workshop\Models\Department::query()->whereIn('id', (array) $request->input('departments', []))->pluck('id');
        $user->branches()->sync($branchIds);
        $user->warehouses()->sync($whIds);
        $user->departments()->sync($depIds);
        AuditLogger::log('user.access_updated', $user, ['branches' => $branchIds, 'warehouses' => $whIds]);

        return back()->with('success', __('Access saved.'));
    }

    // --------------------------------------------------------- integrations

    public function integrations()
    {
        abort_unless(auth()->user()->can('integrations.view'), 403);
        $items = Integration::query()->get()->keyBy('provider');

        return view('system.integrations', ['providers' => IntegrationManager::PROVIDERS, 'items' => $items]);
    }

    public function saveIntegration(Request $request, string $provider)
    {
        abort_unless($request->user()->can('integrations.update'), 403);
        abort_unless(isset(IntegrationManager::PROVIDERS[$provider]), 404);
        $i = Integration::query()->firstOrNew(['provider' => $provider]);
        $old = $i->credentials ?? [];
        $creds = [];
        foreach (IntegrationManager::PROVIDERS[$provider]['fields'] as $f) {
            $v = $request->input('credentials.'.$f);
            // keep existing secrets when the field is left empty
            $creds[$f] = ($v === null || $v === '') && isset($old[$f]) && preg_match('/token|secret|password|key/', $f) ? $old[$f] : $v;
        }
        $i->credentials = $creds;
        $i->is_active = $request->boolean('is_active');
        $i->save();
        AuditLogger::log('integration.updated', $i, ['provider' => $provider, 'active' => $i->is_active]);

        return back()->with('success', __('Integration saved.'));
    }

    public function apiTokens(Request $request)
    {
        abort_unless($request->user()->can('integrations.view'), 403);

        return view('system.api-tokens', ['tokens' => $request->user()->tokens()->latest()->get()]);
    }

    public function createApiToken(Request $request)
    {
        abort_unless($request->user()->can('integrations.create'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'abilities' => ['array'], 'abilities.*' => ['in:read,write']]);
        $token = $request->user()->createToken($data['name'], $data['abilities'] ?? ['read'], now()->addYear());
        AuditLogger::log('api.token_created', $request->user(), ['name' => $data['name']]);

        return back()->with('success', __('Token created. Copy it now; it will not be shown again.'))->with('plain_token', $token->plainTextToken);
    }

    public function revokeApiToken(Request $request, int $id)
    {
        $request->user()->tokens()->whereKey($id)->delete();

        return back()->with('success', __('Token revoked.'));
    }

    // -------------------------------------------------------------- backups

    /**
     * Backups contain the whole database (every tenant). Only the platform super admin may
     * manage them, except on a single-company (on-premise) install where the company
     * administrator owns all the data.
     */
    protected function canManageBackups(string $perm): bool
    {
        $u = auth()->user();
        if ($u->is_super_admin) {
            return true;
        }

        return $u->can($perm) && $u->hasRole('Company Admin') && \App\Modules\Core\Models\Company::query()->count() === 1;
    }

    public function backups()
    {
        abort_unless($this->canManageBackups('backups.view'), 403);
        $files = collect(Storage::disk('local')->files('backups'))->filter(fn ($f) => str_ends_with($f, '.zip') || str_ends_with($f, '.sql') || str_ends_with($f, '.gz'))
            ->map(fn ($f) => ['name' => basename($f), 'size' => Storage::disk('local')->size($f), 'time' => Storage::disk('local')->lastModified($f)])
            ->sortByDesc('time')->values();

        return view('system.backups', compact('files'));
    }

    public function runBackup()
    {
        abort_unless($this->canManageBackups('backups.create'), 403);
        Artisan::call('erp:backup');
        AuditLogger::log('backup.created', null);

        return back()->with('success', trim(Artisan::output()) ?: __('Backup created.'));
    }

    public function downloadBackup(string $file)
    {
        abort_unless($this->canManageBackups('backups.view'), 403);
        $path = 'backups/'.basename($file);
        abort_unless(Storage::disk('local')->exists($path), 404);
        AuditLogger::log('backup.downloaded', null, ['file' => $file]);

        return Storage::disk('local')->download($path);
    }

    public function billing()
    {
        $company = tenant()->company()->load('plan');

        return view('system.billing', [
            'company' => $company, 'plans' => \App\Modules\Core\Models\Plan::query()->where('is_active', true)->orderBy('sort')->get(),
            'subscriptions' => $company->subscriptions()->with('plan')->latest()->get(),
            'domainRequests' => \App\Modules\Saas\Models\DomainRequest::query()->where('company_id', $company->id)->latest()->get(),
            'invoices' => \App\Modules\Core\Models\SubscriptionInvoice::query()->where('company_id', $company->id)->where('status', '!=', 'cancelled')->latest('issue_date')->latest('id')->limit(24)->get(),
            'usage' => [
                'users' => User::query()->where('company_id', $company->id)->whereNull('customer_id')->count(),
                'branches' => Branch::query()->count(),
                'warehouses' => Warehouse::query()->count(),
                'storage_mb' => round(\App\Modules\Core\Models\Attachment::query()->sum('size') / 1048576, 1),
            ],
        ]);
    }

    /** The client views / prints its own platform invoice. */
    public function billingInvoice(int $invoice, string $format = 'a4')
    {
        abort_unless(auth()->user()->can('settings.view'), 403);
        $inv = \App\Modules\Core\Models\SubscriptionInvoice::query()->where('company_id', tenant()->id())->where('status', '!=', 'cancelled')->findOrFail($invoice);

        return \App\Modules\Saas\Http\Controllers\BillingController::render($inv, $format);
    }
}
