<?php

namespace App\Http\Controllers;

use App\Support\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use PragmaRX\Google2FA\Google2FA;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $sessions = config('session.driver') === 'database'
            ? DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get()
            : collect();
        $qr = null;
        if ($user->two_factor_secret && ! $user->two_factor_confirmed_at) {
            $url = (new Google2FA)->getQRCodeUrl(config('app.name'), $user->email, $user->two_factor_secret);
            $qr = \App\Support\Codes::qr($url, 4);
        }
        $layout = $user->isCustomer() ? 'layouts.portal' : 'layouts.app';

        return view('profile.edit', compact('user', 'sessions', 'qr', 'layout'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'locale' => ['required', 'in:ar,en'],
            'theme' => ['required', 'in:light,dark'],
        ]);
        $user->update($data);
        $request->session()->put('locale', $data['locale']);

        return back()->with('success', __('Profile updated.'));
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers(), 'different:current_password'],
        ]);
        $request->user()->forceFill(['password' => $request->input('password'), 'password_changed_at' => now()])->save();
        Auth::logoutOtherDevices($request->input('password'));
        AuditLogger::log('auth.password_changed', $request->user());

        return back()->with('success', __('Password changed. Other sessions were signed out.'));
    }

    public function theme(Request $request)
    {
        $request->validate(['theme' => ['required', 'in:light,dark']]);
        $request->user()->forceFill(['theme' => $request->input('theme')])->saveQuietly();

        return response()->json(['ok' => true]);
    }

    public function enableTwoFactor(Request $request)
    {
        $user = $request->user();
        $user->forceFill(['two_factor_secret' => (new Google2FA)->generateSecretKey(32), 'two_factor_confirmed_at' => null])->save();

        return back()->with('info', __('Scan the QR code with your authenticator app, then enter the code to confirm.'));
    }

    public function confirmTwoFactor(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $user = $request->user();
        if (! $user->two_factor_secret || ! (new Google2FA)->verifyKey($user->two_factor_secret, preg_replace('/\s+/', '', $request->input('code')), 1)) {
            return back()->withErrors(['code' => __('The verification code is invalid.')]);
        }
        $codes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))->all();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $codes])->save();
        AuditLogger::log('auth.2fa_enabled', $user);

        return back()->with('success', __('Two-factor authentication enabled.'))->with('recovery_codes', $codes);
    }

    public function disableTwoFactor(Request $request)
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->save();
        AuditLogger::log('auth.2fa_disabled', $request->user());

        return back()->with('success', __('Two-factor authentication disabled.'));
    }

    public function logoutOtherSessions(Request $request)
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        Auth::logoutOtherDevices($request->input('current_password'));
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        }

        return back()->with('success', __('Other sessions have been signed out.'));
    }
}
