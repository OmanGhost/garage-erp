<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Models\LoginHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class AuthTokenController extends Controller
{
    /** Exchange credentials (+ 2FA code when enabled) for a Sanctum token. */
    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required'], 'device_name' => ['required', 'string', 'max:100'], 'code' => ['nullable', 'string']]);
        $user = User::query()->where('email', strtolower($data['email']))->first();
        $ok = $user && $user->is_active && Hash::check($data['password'], $user->password)
            && (! $user->hasTwoFactor() || (! empty($data['code']) && (new Google2FA)->verifyKey($user->two_factor_secret, $data['code'], 1)));
        LoginHistory::create(['user_id' => $user?->id, 'company_id' => $user?->company_id, 'email' => $data['email'], 'ip_address' => $request->ip(),
            'user_agent' => 'API: '.mb_substr($data['device_name'], 0, 200), 'successful' => $ok, 'failure_reason' => $ok ? null : 'api_invalid', 'created_at' => now()]);
        if (! $ok) {
            return response()->json(['message' => __('These credentials do not match our records.')], 422);
        }
        $abilities = $user->isCustomer() ? ['read'] : ['read', 'write'];

        return response()->json(['token' => $user->createToken($data['device_name'], $abilities, now()->addDays(90))->plainTextToken, 'abilities' => $abilities]);
    }

    public function destroy(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }
}
