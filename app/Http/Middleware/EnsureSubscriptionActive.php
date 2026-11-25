<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Expired / suspended subscriptions become read-only: GET requests still work
 * (data access), state-changing requests are rejected.
 */
class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next)
    {
        $company = tenant()->company();
        if ($company && ! $request->user()?->is_super_admin && ! $company->subscriptionActive() && ! $request->isMethodSafe()
            && ! $request->routeIs('logout', 'billing.*')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('Your subscription has expired. The system is in read-only mode.')], 402);
            }

            return back()->with('error', __('Your subscription has expired. The system is in read-only mode.'));
        }

        return $next($request);
    }
}
