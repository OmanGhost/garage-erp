<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Core\Models\Plan;
use App\Modules\Core\Notifications\SystemMessage;
use App\Modules\Saas\Models\TrialRequest;
use App\Modules\Saas\Services\TrialInspector;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Public marketing website: landing page (features, plans, FAQ), free-trial
 * request, sitemap.xml and robots.txt. Arabic lives at "/", English at "/en".
 */
class SiteController extends Controller
{
    protected function useLocale(string $locale): void
    {
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
        app()->setLocale($locale);
        Carbon::setLocale($locale);
    }

    protected function plans()
    {
        return Plan::query()->where('is_active', true)->orderBy('sort')->get();
    }

    /** "/" for guests (Arabic); signed-in users go to their workspace. */
    public function root(Request $request)
    {
        if ($user = $request->user()) {
            return redirect()->route($user->isCustomer() ? 'portal.dashboard' : ($user->is_super_admin && ! $user->company_id ? 'admin.dashboard' : 'dashboard'));
        }

        return $this->home($request, 'ar');
    }

    public function home(Request $request, string $locale = 'en')
    {
        $this->useLocale($locale);

        return response()->view('site.home', ['plans' => $this->plans(), 'page' => 'home']);
    }

    public function trial(Request $request, string $locale = 'ar')
    {
        $this->useLocale($locale);

        return response()->view('site.trial', [
            'plans' => $this->plans(),
            'selectedPlan' => $request->query('plan'),
            'formToken' => Crypt::encryptString((string) now()->timestamp),
            'page' => 'trial',
        ]);
    }

    public function storeTrial(Request $request, TrialInspector $inspector)
    {
        $locale = $request->input('locale') === 'en' ? 'en' : 'ar';
        $this->useLocale($locale);

        // spam protection: honeypot field and a minimum time on the form
        $fillSeconds = $this->formSeconds((string) $request->input('_ft'));
        if (filled($request->input('website')) || $fillSeconds === null || $fillSeconds < 3 || $fillSeconds > 86400) {
            return redirect()->route($locale === 'en' ? 'site.thanks.en' : 'site.thanks');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'company_name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'min:8', 'max:30', 'regex:/^[0-9+\s()-]+$/'],
            'city' => ['nullable', 'string', 'max:80'],
            'business_type' => ['required', Rule::in(TrialRequest::BUSINESS_TYPES)],
            'branches' => ['nullable', 'integer', 'min:1', 'max:500'],
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ], [], [
            'company_name' => __('site.form.company_name'), 'business_type' => __('site.form.business_type'),
            'branches' => __('site.form.branches'), 'consent' => __('site.form.consent_short'),
        ]);

        // one open request per email is enough
        $existing = TrialRequest::query()->where('email', strtolower($data['email']))->whereIn('status', ['new', 'contacted'])->first();
        if (! $existing) {
            $trial = TrialRequest::create([
                'name' => $data['name'], 'company_name' => $data['company_name'], 'email' => strtolower($data['email']),
                'phone' => omani_phone($data['phone']), 'city' => $data['city'] ?? null, 'business_type' => $data['business_type'],
                'branches' => $data['branches'] ?? null, 'plan_id' => $data['plan_id'] ?? null, 'message' => $data['message'] ?? null,
                'locale' => $locale, 'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'source' => mb_substr((string) ($request->input('utm_source') ?: ($request->input('_client.ref') ?: $request->headers->get('referer'))), 0, 120) ?: null,
                'meta' => $inspector->fromRequest($request, $fillSeconds),
            ]);
            // IP country / ISP / VPN flags and email MX check run after the visitor has the response
            dispatch(function () use ($trial) {
                $fresh = $trial->fresh();
                if ($fresh && ! $fresh->checked_at) {
                    app(TrialInspector::class)->enrich($fresh);
                }
            })->afterResponse();
            $admins = User::query()->where('is_super_admin', true)->where('is_active', true)->get();
            Notification::send($admins, new SystemMessage(
                __('New trial request: :c', ['c' => $trial->company_name]), $trial->name.' · '.$trial->phone, route('admin.trials.index'), 'success'
            ));
            // optional email alert to the sales team (queued; a mail failure never blocks the visitor)
            if ($to = SiteSettings::get('trial_notify_email')) {
                $body = "New trial request\n\nCompany: {$trial->company_name}\nName: {$trial->name}\nPhone: {$trial->phone}\nEmail: {$trial->email}\n"
                    ."City: {$trial->city}\nBusiness: {$trial->business_type}\nBranches: {$trial->branches}\n\n{$trial->message}\n\n".route('admin.trials.index');
                dispatch(fn () => \Illuminate\Support\Facades\Mail::raw($body, fn ($m) => $m->to($to)->subject('New trial request: '.$trial->company_name)));
            }
        }

        return redirect()->route($locale === 'en' ? 'site.thanks.en' : 'site.thanks');
    }

    /** Seconds the visitor spent on the form (null when the token is missing or forged). */
    protected function formSeconds(string $token): ?int
    {
        try {
            return now()->timestamp - (int) Crypt::decryptString($token);
        } catch (\Throwable) {
            return null;
        }
    }

    public function thanks(string $locale = 'ar')
    {
        $this->useLocale($locale);

        return response()->view('site.thanks', ['page' => 'thanks']);
    }

    public function sitemap()
    {
        $lastmod = Carbon::parse(Plan::query()->max('updated_at') ?? now());
        $pages = [
            ['ar' => route('site.home'), 'en' => route('site.home.en'), 'priority' => '1.0', 'freq' => 'weekly'],
            ['ar' => route('site.trial'), 'en' => route('site.trial.en'), 'priority' => '0.8', 'freq' => 'monthly'],
        ];

        return response()->view('site.sitemap', compact('pages', 'lastmod'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        $lines = ['User-agent: *'];
        if (SiteSettings::get('indexing_enabled') === '1') {
            $lines[] = 'Allow: /$';
            $lines[] = 'Allow: /en';
            $lines[] = 'Allow: /trial';
            $lines[] = 'Allow: /shop/';
            foreach (['/dashboard', '/admin', '/portal', '/api/', '/m/', '/login', '/forgot-password', '/reset-password', '/two-factor', '/livewire', '/storage/', '/q/', '/fa/', '/r/', '/i/', '/track/', '/pay/'] as $p) {
                $lines[] = 'Disallow: '.$p;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('site.sitemap');
        } else {
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
