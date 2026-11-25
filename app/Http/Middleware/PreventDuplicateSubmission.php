<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Idempotency for state-changing requests. Forms carry a one-time `_idem`
 * token (see <x-idem/>); API clients may send an `Idempotency-Key` header.
 * A replayed key is rejected so financial operations are never duplicated.
 */
class PreventDuplicateSubmission
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }
        $key = $request->header('Idempotency-Key') ?: $request->input('_idem');
        if (! $key || strlen($key) > 100) {
            return $next($request);
        }
        try {
            DB::table('idempotency_keys')->insert([
                'company_id' => tenant()->id(), 'user_id' => $request->user()?->id ?? 0, 'key' => $key,
                'route' => substr((string) $request->route()?->getName(), 0, 190), 'created_at' => now(),
            ]);
        } catch (QueryException) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('This request was already processed.')], 409);
            }

            return back()->with('warning', __('This request was already processed.'));
        }

        $response = $next($request);
        // allow a retry when the request failed validation / business rules
        if ($response->getStatusCode() >= 400 || session()->has('errors') || session()->has('error')) {
            DB::table('idempotency_keys')->where('user_id', $request->user()?->id ?? 0)->where('key', $key)->delete();
        }

        return $response;
    }
}
