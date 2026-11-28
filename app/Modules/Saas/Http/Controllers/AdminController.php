<?php

namespace App\Modules\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Plan;
use App\Modules\Core\Models\Subscription;
use App\Modules\Core\Models\SubscriptionInvoice;
use App\Modules\Core\Services\TenantProvisioner;
use App\Modules\Saas\Models\TrialRequest;
use App\Modules\Saas\Services\TrialInspector;
use App\Support\Audit\AuditLogger;
use App\Support\SiteSettings;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * SaaS super-admin: tenants, plans, subscriptions & renewals, module activation,
 * limits, suspension and secure "enter tenant" support access.
 */
class AdminController extends Controller
{
    public function dashboard()
    {
        tenant()->forget();
        $companies = Company::query()->with('plan')->withCount(['users', 'branches'])->latest()->limit(10)->get();
        $mrr = Money::sum(Subscription::query()->whereIn('status', ['active'])->get()->map(fn ($s) => $s->billing_cycle === 'yearly' ? Money::div($s->amount, 12) : $s->amount));

        $active = Company::query()->with('plan')->where('is_active', true)->get();
        $adoption = collect(config('erp.modules'))->filter(fn ($m) => ! empty($m['new']))
            ->map(fn ($m, $k) => ['label' => $m['label'], 'n' => $active->filter(fn ($c) => $c->moduleEnabled($k))->count()]);

        return view('saas::dashboard', [
            'adoption' => $adoption, 'activeCount' => $active->count(),
            'companies' => $companies, 'plans' => Plan::query()->orderBy('sort')->get(), 'mrr' => $mrr,
            'stats' => [
                // KPIs over all clients (the table shows the latest 10 only)
                'tenants' => Company::query()->count(),
                'active' => Company::query()->where('is_active', true)->count(),
                'trial' => Company::query()->where('subscription_status', 'trial')->count(),
                'expiring' => Company::query()->where('is_active', true)->where(fn ($q) => $q->whereBetween('subscription_ends_at', [now(), now()->addDays(14)])
                    ->orWhere(fn ($t) => $t->where('subscription_status', 'trial')->whereBetween('trial_ends_at', [now(), now()->addDays(14)])))->count(),
                'users' => User::query()->whereNull('customer_id')->where('is_super_admin', false)->count(),
            ],
        ]);
    }

    public function createCompany()
    {
        return view('saas::company-create', ['plans' => Plan::query()->where('is_active', true)->orderBy('sort')->get()]);
    }

    public function storeCompany(Request $request, TenantProvisioner $provisioner)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'], 'name_en' => ['nullable', 'string', 'max:190'], 'slug' => ['nullable', 'alpha_dash', 'max:80', 'unique:companies,slug'],
            'cr_number' => ['nullable', 'string', 'max:50'], 'vat_number' => ['nullable', 'string', 'max:50'], 'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email'],
            'plan_id' => ['nullable', 'exists:plans,id'], 'billing_cycle' => ['required', 'in:monthly,yearly'],
            'admin_name' => ['required', 'string', 'max:190'], 'admin_email' => ['required', 'email', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:10'],
        ]);
        $company = $provisioner->provision($data);
        AuditLogger::log('saas.tenant_created', $company, ['plan' => $data['plan_id'] ?? null]);

        return redirect()->route('admin.clients.show', $company->id)->with('success', __('Tenant created and provisioned.'));
    }

    public function updateCompany(Request $request, Company $company)
    {
        $data = $request->validate([
            'plan_id' => ['nullable', 'exists:plans,id'], 'subscription_status' => ['required', 'in:trial,active,past_due,suspended,cancelled'],
            'subscription_ends_at' => ['nullable', 'date'], 'trial_ends_at' => ['nullable', 'date'], 'is_active' => ['nullable', 'boolean'],
            'custom_domain' => ['nullable', 'string', 'max:190', Rule::unique('companies', 'custom_domain')->ignore($company->id)],
            'modules' => ['nullable', 'array'], 'modules.*' => [Rule::in(array_keys(config('erp.modules')))], 'override_modules' => ['nullable', 'boolean'],
        ]);
        $company->fill(collect($data)->except(['modules', 'override_modules', 'is_active'])->all());
        $company->is_active = $request->boolean('is_active');
        $company->enabled_modules = $request->boolean('override_modules') ? self::normalizeModules($data['modules'] ?? []) : null;
        $company->save();
        AuditLogger::log('saas.tenant_updated', $company, $data);

        return back()->with('success', __('Tenant updated.'));
    }

    /** Renew subscription & issue the subscription invoice. */
    public function renew(Request $request, Company $company)
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id'], 'billing_cycle' => ['required', 'in:monthly,yearly'], 'paid' => ['nullable', 'boolean']]);
        $inv = app(\App\Modules\Saas\Services\BillingService::class)->renew($company, Plan::query()->findOrFail($data['plan_id']), $data['billing_cycle'],
            ['paid' => $request->boolean('paid')]);

        return back()->with('success', __('Subscription renewed. Invoice :n issued.', ['n' => $inv->number]))
            ->with('print_voucher', route('admin.billing.print', $inv))->with('print_label', __('Print invoice'));
    }

    /** Super admin enters a tenant (support access, audited). */
    public function enter(Request $request, Company $company)
    {
        $request->session()->put('admin_company_id', $company->id);
        $request->session()->forget('branch_id');
        AuditLogger::log('saas.enter_tenant', $company);

        return redirect()->route('dashboard');
    }

    public function leave(Request $request)
    {
        $request->session()->forget(['admin_company_id', 'branch_id']);

        return redirect()->route('admin.dashboard');
    }

    public function plans()
    {
        return view('saas::plans', ['plans' => Plan::query()->orderBy('sort')->get(),
            'clients' => Company::query()->selectRaw('plan_id, COUNT(*) n')->groupBy('plan_id')->pluck('n', 'plan_id')]);
    }

    /** Core modules always on; a feature whose parent module is off is dropped. */
    public static function normalizeModules(array $selected): array
    {
        $defs = config('erp.modules');
        $mods = array_values(array_unique(array_merge($selected, array_keys(array_filter($defs, fn ($m) => ! empty($m['core']))))));

        return array_values(array_filter($mods, fn ($k) => isset($defs[$k]) && (empty($defs[$k]['requires']) || in_array($defs[$k]['requires'], $mods, true))));
    }

    public function savePlan(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'exists:plans,id'], 'code' => ['required', 'alpha_dash', 'max:50'], 'name' => ['required', 'string', 'max:100'], 'name_ar' => ['nullable', 'string', 'max:100'],
            'price_monthly' => ['required', 'numeric', 'min:0'], 'price_yearly' => ['required', 'numeric', 'min:0'], 'trial_days' => ['required', 'integer', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'], 'max_branches' => ['nullable', 'integer', 'min:1'], 'max_warehouses' => ['nullable', 'integer', 'min:1'],
            'max_storage_mb' => ['nullable', 'integer', 'min:10'], 'modules' => ['nullable', 'array'], 'modules.*' => [Rule::in(array_keys(config('erp.modules')))],
            'is_active' => ['nullable', 'boolean'], 'sort' => ['nullable', 'integer'],
            'allow_custom_domain' => ['nullable', 'boolean'], 'domain_fee' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['allow_custom_domain'] = $request->boolean('allow_custom_domain');
        $plan = ! empty($data['id']) ? Plan::query()->findOrFail($data['id']) : new Plan;
        $plan->fill(collect($data)->except(['id', 'modules'])->all() + ['is_active' => $request->boolean('is_active'), 'modules' => self::normalizeModules($data['modules'] ?? [])])->save();

        return back()->with('success', __('Plan saved.'));
    }

    // ------------------------------------------------- public website: trial requests

    public function trials(Request $request, TrialInspector $inspector)
    {
        $status = $request->query('status');
        $trials = TrialRequest::query()->with('plan', 'company')
            ->when(in_array($status, TrialRequest::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($request->query('country'), fn ($q, $c) => $q->where('country_code', strtoupper((string) $c)))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('company_name', 'like', "%$t%")->orWhere('name', 'like', "%$t%")
                ->orWhere('email', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")->orWhere('ip_address', 'like', "%$t%")))
            ->latest()->paginate(25)->withQueryString();

        // duplicate counters for the whole page in three queries
        $dup = [];
        foreach (['email' => 'email', 'phone' => 'phone', 'ip' => 'ip_address'] as $k => $col) {
            $values = $trials->getCollection()->pluck($col)->filter()->unique()->values();
            $dup[$k] = $values->isEmpty() ? collect() : TrialRequest::query()->whereIn($col, $values)->selectRaw("$col as v, count(*) as c")->groupBy($col)->pluck('c', 'v');
        }
        $assessments = $trials->getCollection()->mapWithKeys(fn (TrialRequest $t) => [$t->id => $inspector->assess($t, [
            'email' => max(0, ($dup['email'][$t->email] ?? 1) - 1),
            'phone' => max(0, ($dup['phone'][$t->phone] ?? 1) - 1),
            'ip' => $t->ip_address ? max(0, ($dup['ip'][$t->ip_address] ?? 1) - 1) : 0,
        ])]);

        return view('saas::trials', [
            'trials' => $trials,
            'assessments' => $assessments,
            'counts' => TrialRequest::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
            'countries' => TrialRequest::query()->whereNotNull('country_code')->selectRaw('country_code, count(*) as c')->groupBy('country_code')->orderByDesc('c')->pluck('c', 'country_code'),
        ]);
    }

    public function showTrial(TrialRequest $trial, TrialInspector $inspector)
    {
        $trial->load('plan', 'company', 'handler');
        $related = TrialRequest::query()->whereKeyNot($trial->id)
            ->where(fn ($q) => $q->where('email', $trial->email)->orWhere('phone', $trial->phone)
                ->when($trial->ip_address, fn ($w) => $w->orWhere('ip_address', $trial->ip_address)))
            ->latest()->limit(10)->get();

        return view('saas::trial-show', [
            't' => $trial,
            'a' => $inspector->assess($trial, $trial->duplicates()),
            'related' => $related,
            'plans' => Plan::query()->orderBy('sort')->get(),
        ]);
    }

    /** Run the network checks again (IP intelligence + email domain). */
    public function recheckTrial(TrialRequest $trial, TrialInspector $inspector)
    {
        $inspector->enrich($trial);

        return back()->with('success', __('Verification data updated.'));
    }

    public function updateTrial(Request $request, TrialRequest $trial)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['new', 'contacted', 'rejected'])], 'admin_notes' => ['nullable', 'string', 'max:2000']]);
        if ($trial->status === 'provisioned') {
            return back()->with('warning', __('This request was already processed.'));
        }
        $trial->update($data + ['handled_at' => now(), 'handled_by' => auth()->id()]);
        AuditLogger::log('saas.trial_updated', $trial, ['status' => $data['status']]);

        return back()->with('success', __('Saved successfully.'));
    }

    /** Turn a trial request into a provisioned trial company with its admin user. */
    public function provisionTrial(Request $request, TrialRequest $trial, TenantProvisioner $provisioner)
    {
        if ($trial->status === 'provisioned') {
            return back()->with('warning', __('This request was already processed.'));
        }
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);
        if (User::query()->where('email', $trial->email)->exists()) {
            return back()->with('error', __('A user with this email already exists.'));
        }
        $password = Str::password(12, symbols: false);
        $company = $provisioner->provision([
            'name' => $trial->company_name, 'name_en' => $trial->company_name, 'phone' => $trial->phone, 'email' => $trial->email,
            'city' => $trial->city ?: 'Muscat', 'locale' => $trial->locale, 'plan_id' => $data['plan_id'],
            'admin_name' => $trial->name, 'admin_email' => $trial->email, 'admin_password' => $password,
        ]);
        tenant()->forget();
        $company->update(['source' => 'trial_request', 'contact_name' => $trial->name, 'contact_phone' => $trial->phone,
            'admin_notes' => $trial->message ? __('Trial request').': '.$trial->message : null]);
        $trial->update(['status' => 'provisioned', 'company_id' => $company->id, 'plan_id' => $data['plan_id'], 'handled_at' => now(), 'handled_by' => auth()->id()]);
        AuditLogger::log('saas.trial_provisioned', $company, ['trial_request' => $trial->id]);

        return redirect()->route('admin.clients.show', $company->id)
            ->with('success', __('Trial company created. Login: :e / temporary password: :p', ['e' => $trial->email, 'p' => $password]));
    }

    // ------------------------------------------------- public website: SEO & contact settings

    /* ---------------- website contact messages ---------------- */

    public function messages(Request $request)
    {
        $status = in_array($request->query('status'), \App\Modules\Saas\Models\ContactMessage::STATUSES, true) ? $request->query('status') : null;

        return view('saas::messages', [
            'messages' => \App\Modules\Saas\Models\ContactMessage::query()->when($status, fn ($q) => $q->where('status', $status))->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => \App\Modules\Saas\Models\ContactMessage::query()->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function showMessage(\App\Modules\Saas\Models\ContactMessage $message)
    {
        return view('saas::message', ['m' => $message]);
    }

    public function updateMessage(Request $request, \App\Modules\Saas\Models\ContactMessage $message)
    {
        $d = $request->validate(['status' => ['required', \Illuminate\Validation\Rule::in(\App\Modules\Saas\Models\ContactMessage::STATUSES)], 'admin_notes' => ['nullable', 'string', 'max:5000']]);
        $message->update($d);

        return back()->with('success', __('Saved.'));
    }

    public function siteSettings()
    {
        return view('saas::site-settings', ['s' => SiteSettings::all()]);
    }

    public function updateSiteSettings(Request $request)
    {
        $text = ['nullable', 'string', 'max:300'];
        $data = $request->validate([
            'site_name_ar' => ['required', 'string', 'max:120'], 'site_name_en' => ['required', 'string', 'max:120'],
            'seo_title_ar' => ['required', 'string', 'max:120'], 'seo_title_en' => ['required', 'string', 'max:120'],
            'seo_description_ar' => ['required', 'string', 'max:320'], 'seo_description_en' => ['required', 'string', 'max:320'],
            'seo_keywords_ar' => $text, 'seo_keywords_en' => $text,
            'og_image' => ['nullable', 'string', 'max:300'], 'og_image_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'contact_email' => ['nullable', 'email'], 'contact_phone' => ['nullable', 'string', 'max:40'], 'contact_whatsapp' => ['nullable', 'string', 'max:20'],
            'contact_address_ar' => $text, 'contact_address_en' => $text,
            'social_x' => ['nullable', 'url', 'max:300'], 'social_instagram' => ['nullable', 'url', 'max:300'],
            'social_linkedin' => ['nullable', 'url', 'max:300'], 'social_youtube' => ['nullable', 'url', 'max:300'],
            'google_site_verification' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'bing_site_verification' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'google_analytics_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/'],
            'trial_notify_email' => ['nullable', 'email'],
            'clients' => ['nullable', 'string', 'max:20000'],
            'terms_ar' => ['nullable', 'string', 'max:60000'], 'terms_en' => ['nullable', 'string', 'max:60000'],
            'privacy_ar' => ['nullable', 'string', 'max:60000'], 'privacy_en' => ['nullable', 'string', 'max:60000'],
        ]);
        foreach (['terms', 'privacy'] as $doc) {
            if (($data[$doc.'_ar'] ?? '') !== SiteSettings::get($doc.'_ar') || ($data[$doc.'_en'] ?? '') !== SiteSettings::get($doc.'_en')) {
                $data[$doc.'_updated_at'] = now()->toDateString();
            }
        }
        if ($request->hasFile('og_image_file')) {
            $data['og_image'] = '/storage/'.$request->file('og_image_file')->store('site', 'public');
        }
        unset($data['og_image_file']);
        $data['og_image'] = $data['og_image'] ?: '/images/og-default.png';
        $data['indexing_enabled'] = $request->boolean('indexing_enabled') ? '1' : '0';
        SiteSettings::set(array_map(fn ($v) => $v ?? '', $data));
        AuditLogger::log('saas.site_settings_updated', null, ['indexing' => $data['indexing_enabled']]);

        return back()->with('success', __('Settings saved.'));
    }
}
