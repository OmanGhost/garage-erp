<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** SaaS module activation per subscription plan: ->middleware('module:inventory') */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module)
    {
        $company = tenant()->company();
        if ($company && ! $company->moduleEnabled($module)) {
            abort(403, __('The :m module is not enabled in your subscription plan.', ['m' => __(config("erp.modules.$module.label", $module))]));
        }

        return $next($request);
    }
}
