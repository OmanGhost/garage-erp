<?php

namespace App\Http\Middleware;

use App\Modules\Saas\Services\DomainService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Requests on a client's own domain (e.g. erp.mygarage.om):
 *  - the login page shows that company's name and logo, and "/" opens the login instead of the marketing site;
 *  - only that company's users (and platform admins) can work on it: other accounts are signed out.
 */
class ResolveCustomDomain
{
    public function handle(Request $request, Closure $next)
    {
        $host = strtolower($request->getHost());
        $companyId = Cache::remember('domain-host:'.$host, 300, fn () => DomainService::companyForHost($host)?->id ?? 0);
        if (! $companyId) {
            return $next($request);
        }
        $company = \App\Modules\Core\Models\Company::query()->find($companyId);
        if (! $company) {
            return $next($request);
        }
        app()->instance('domain.company', $company);
        view()->share('domainCompany', $company);

        $user = $request->user();
        if ($user && ! $user->is_super_admin && (int) $user->company_id !== (int) $company->id) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors(['email' => __('This account does not belong to :c.', ['c' => $company->displayName()])]);
        }
        if (! $user && $request->is('/', 'en')) {
            return redirect('/login');
        }

        return $next($request);
    }
}
