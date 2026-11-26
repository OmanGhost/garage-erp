<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Models\LoginHistory;
use App\Support\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:190'],
        ]);
        $email = strtolower($data['email']);
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $this->history($user, $email, false, 'invalid_credentials');

            return back()->withInput($request->only('email'))->withErrors(['email' => __('These credentials do not match our records.')]);
        }
        if (! $user->is_active) {
            $this->history($user, $email, false, 'inactive');

            return back()->withInput($request->only('email'))->withErrors(['email' => __('Your account is disabled.')]);
        }
        // on a client's own domain only that client's users (and platform admins) can sign in
        $domainCompany = app()->bound('domain.company') ? app('domain.company') : null;
        if ($domainCompany && ! $user->is_super_admin && (int) $user->company_id !== (int) $domainCompany->id) {
            $this->history($user, $email, false, 'wrong_domain');

            return back()->withInput($request->only('email'))->withErrors(['email' => __('This account does not belong to :c.', ['c' => $domainCompany->displayName()])]);
        }
        if ($user->hasTwoFactor()) {
            $request->session()->put('login.2fa', ['id' => $user->id, 'remember' => $request->boolean('remember'), 'at' => time()]);

            return redirect()->route('two-factor.challenge');
        }

        return $this->complete($request, $user, $request->boolean('remember'));
    }

    public function showTwoFactor(Request $request)
    {
        abort_unless($request->session()->has('login.2fa'), 403);

        return view('auth.two-factor');
    }

    public function twoFactor(Request $request)
    {
        $pending = $request->session()->get('login.2fa');
        if (! $pending || time() - $pending['at'] > 300) {
            $request->session()->forget('login.2fa');

            return redirect()->route('login')->withErrors(['email' => __('The verification session expired. Please sign in again.')]);
        }
        $request->validate(['code' => ['nullable', 'string', 'max:20'], 'recovery_code' => ['nullable', 'string', 'max:40']]);
        $user = User::query()->findOrFail($pending['id']);
        $ok = false;
        if ($code = preg_replace('/\s+/', '', (string) $request->input('code'))) {
            $ok = (new Google2FA)->verifyKey($user->two_factor_secret, $code, 1);
        } elseif ($rc = trim((string) $request->input('recovery_code'))) {
            $codes = $user->two_factor_recovery_codes ?? [];
            if (in_array($rc, $codes, true)) {
                $ok = true;
                $user->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$rc]))])->save();
                AuditLogger::log('auth.recovery_code_used', $user);
            }
        }
        if (! $ok) {
            $this->history($user, $user->email, false, 'invalid_2fa');

            return back()->withErrors(['code' => __('The verification code is invalid.')]);
        }
        $request->session()->forget('login.2fa');

        return $this->complete($request, $user, (bool) $pending['remember']);
    }

    protected function complete(Request $request, User $user, bool $remember)
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        $this->history($user, $user->email, true);
        if ($user->locale) {
            $request->session()->put('locale', $user->locale);
        }

        if ($user->is_super_admin && ! $user->company_id) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->intended($user->isCustomer() ? route('portal.dashboard') : route('dashboard'));
    }

    protected function history(?User $user, string $email, bool $ok, ?string $reason = null): void
    {
        LoginHistory::create([
            'user_id' => $user?->id, 'company_id' => $user?->company_id, 'email' => $email,
            'ip_address' => request()->ip(), 'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'successful' => $ok, 'failure_reason' => $reason, 'created_at' => now(),
        ]);
        if (! $ok && config('logging.channels.siem.enabled')) {
            logger()->channel('siem')->warning('auth.failed', ['email' => $email, 'ip' => request()->ip(), 'reason' => $reason]);
        }
    }

    public function logout(Request $request)
    {
        AuditLogger::log('auth.logout', $request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showForgot()
    {
        return view('auth.forgot-password');
    }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        // always the same answer (no account enumeration)
        return back()->with('success', __('If the email exists, a reset link has been sent.'));
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->mixedCase()->numbers()],
        ]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'password_changed_at' => now()])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', __('Your password has been reset.'))
            : back()->withErrors(['email' => __($status)]);
    }
}
