<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** usertype:staff | usertype:customer | usertype:super */
class EnsureUserType
{
    public function handle(Request $request, Closure $next, string $type)
    {
        $user = $request->user();
        $ok = match ($type) {
            'staff' => $user && ! $user->isCustomer() && ($user->company_id || $user->is_super_admin),
            'customer' => $user && $user->isCustomer(),
            'super' => $user && $user->is_super_admin,
            default => false,
        };
        if (! $ok) {
            if ($type === 'staff' && $user?->isCustomer()) {
                return redirect()->route('portal.dashboard');
            }
            if ($type === 'staff' && $user?->is_super_admin && ! tenant()->id()) {
                return redirect()->route('admin.dashboard');
            }
            abort(403);
        }

        return $next($request);
    }
}
