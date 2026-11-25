<?php

namespace App\Http\Middleware;

use App\Modules\Core\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Establishes the tenant (company) & branch context for every authenticated
 * request. Super admins may switch company; other users are locked to theirs.
 */
class SetTenant
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }
        if (! $user->is_active) {
            abort_unless($request->hasSession(), 403, __('Your account is disabled.'));
            Auth::guard('web')->logout();

            return redirect()->route('login')->withErrors(['email' => __('Your account is disabled.')]);
        }

        // API (token) requests have no session: fall back to the user's own company / branch
        $session = $request->hasSession() ? $request->session() : null;
        $companyId = $user->is_super_admin ? (int) $session?->get('admin_company_id') ?: $user->company_id : $user->company_id;
        if (! $companyId) {
            tenant()->set(null);
            // platform admin without a selected company: company pages make no sense
            $free = ['admin.*', 'logout', 'profile.*', 'locale.*', 'two-factor*', 'livewire.*', 'dashboard', 'lookup.*', 'notifications.*', 'manifest'];
            if ($user->is_super_admin && $request->route() && ! $request->routeIs(...$free) && ! $request->expectsJson()) {
                return redirect()->route('admin.dashboard');
            }

            return $next($request);
        }
        $company = Company::query()->find($companyId);
        if (! $company || (! $company->is_active && ! $user->is_super_admin)) {
            abort_unless($request->hasSession(), 403, __('Your company account is suspended.'));
            Auth::guard('web')->logout();

            return redirect()->route('login')->withErrors(['email' => __('Your company account is suspended.')]);
        }

        tenant()->set($company->id); // sets Spatie team id first so permission checks work
        // portal customers are restricted by ownership checks, not by branch
        $allowed = $user->isCustomer() ? null : $user->allowedBranchIds();
        $branchId = (int) $session?->get('branch_id') ?: $user->branch_id;
        if ($allowed !== null && $branchId && ! in_array($branchId, $allowed, true)) {
            $branchId = $allowed[0] ?? null;
        }
        tenant()->set($company->id, $branchId ?: null, $allowed);

        if ($company->timezone) {
            config(['app.timezone' => $company->timezone]);
            date_default_timezone_set($company->timezone);
        }

        return $next($request);
    }
}
