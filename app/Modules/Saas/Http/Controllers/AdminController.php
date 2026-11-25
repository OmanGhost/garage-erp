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
        $companies = Company::query()->with('plan')->withCount(['users', 'branches'])->latest()->get();
        $mrr = Money::sum(Subscription::query()->whereIn('status', ['active'])->get()->map(fn ($s) => $s->billing_cycle === 'yearly' ? Money::div($s->amount, 12) : $s->amount));

        return view('saas::dashboard', [
            'companies' => $companies, 'plans' => Plan::query()->orderBy('sort')->get(), 'mrr' => $mrr,
            'stats' => [
                'tenants' => $companies->count(), 'active' => $companies->where('is_active', true)->count(),
                'trial' => $companies->where('subscription_status', 'trial')->count(),
                'expiring' => $companies->filter(fn ($c) => $c->subscription_ends_at && $c->subscription_ends_at->between(now(), now()->addDays(14)))->count(),
                'users' => User::query()->count(),
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

        return redirect()->route('admin.companies.show', $company)->with('success', __('Tenant created and provisioned.'));
    }

    public function showCompany(Company $company)
    {
        return view('saas::company', [
            'c' => $company->load('plan', 'subscriptions.plan'), 'plans' => Plan::query()->orderBy('sort')->get(),
            'usage' => [
                'users' => User::query()->where('company_id', $company->id)->whereNull('customer_id')->count(),
                'branches' => DB::table('branches')->where('company_id', $company->id)->whereNull('deleted_at')->count(),
                'storage_mb' => round(DB::table('attachments')->where('company_id', $company->id)->sum('size') / 1048576, 1),
                'invoices' => DB::table('invoices')->where('company_id', $company->id)->count(),
                'job_cards' => DB::table('job_cards')->where('company_id', $company->id)->count(),
            ],
            'invoices' => SubscriptionInvoice::query()->where('company_id', $company->id)->latest()->get(),
        ]);
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
        $company->enabled_modules = $request->boolean('override_modules') ? array_values($data['modules'] ?? []) : null;
        $company->save();
        AuditLogger::log('saas.tenant_updated', $company, $data);

        return back()->with('success', __('Tenant updated.'));
    }

    /** Renew subscription & issue the subscription invoice. */
    public function renew(Request $request, Company $company)
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id'], 'billing_cycle' => ['required', 'in:monthly,yearly'], 'paid' => ['nullable', 'boolean']]);
        DB::transaction(function () use ($company, $data, $request) {
            $plan = Plan::query()->findOrFail($data['plan_id']);
            $start = $company->subscription_ends_at && $company->subscription_ends_at->isFuture() ? $company->subscription_ends_at->copy() : now();
            $end = $data['billing_cycle'] === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth();
            $amount = $data['billing_cycle'] === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
            $sub = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'billing_cycle' => $data['billing_cycle'], 'status' => 'active',
                'starts_at' => $start->toDateString(), 'ends_at' => $end->toDateString(), 'amount' => $amount]);
            SubscriptionInvoice::create(['company_id' => $company->id, 'subscription_id' => $sub->id, 'number' => 'SUB-'.now()->format('Ymd').'-'.$sub->id,
                'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(), 'amount' => $amount,
                'status' => $request->boolean('paid') ? 'paid' : 'unpaid', 'paid_at' => $request->boolean('paid') ? now() : null]);
            $company->update(['plan_id' => $plan->id, 'subscription_status' => 'active', 'subscription_ends_at' => $end, 'is_active' => true]);
        });

        return back()->with('success', __('Subscription renewed.'));
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
        return view('saas::plans', ['plans' => Plan::query()->orderBy('sort')->get()]);
    }

    public function savePlan(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'exists:plans,id'], 'code' => ['required', 'alpha_dash', 'max:50'], 'name' => ['required', 'string', 'max:100'], 'name_ar' => ['nullable', 'string', 'max:100'],
            'price_monthly' => ['required', 'numeric', 'min:0'], 'price_yearly' => ['required', 'numeric', 'min:0'], 'trial_days' => ['required', 'integer', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'], 'max_branches' => ['nullable', 'integer', 'min:1'], 'max_warehouses' => ['nullable', 'integer', 'min:1'],
            'max_storage_mb' => ['nullable', 'integer', 'min:10'], 'modules' => ['nullable', 'array'], 'modules.*' => [Rule::in(array_keys(config('erp.modules')))],
            'is_active' => ['nullable', 'boolean'], 'sort' => ['nullable', 'integer'],
        ]);
        $plan = ! empty($data['id']) ? Plan::query()->findOrFail($data['id']) : new Plan;
        $plan->fill(collect($data)->except('id')->all() + ['is_active' => $request->boolean('is_active'), 'modules' => array_values($data['modules'] ?? [])])->save();

        return back()->with('success', __('Plan saved.'));
    }

    // ------------------------------------------------- public website: trial requests

    public function trials(Request $request)
    {
        $status = $request->query('status');

        return view('saas::trials', [
            'trials' => TrialRequest::query()->with('plan', 'company', 'handler')
                ->when(in_array($status, TrialRequest::STATUSES, true), fn ($q) => $q->where('status', $status))
                ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('company_name', 'like', "%$t%")->orWhere('name', 'like', "%$t%")
                    ->orWhere('email', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")))
                ->latest()->paginate(25)->withQueryString(),
            'counts' => TrialRequest::query()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'plans' => Plan::query()->orderBy('sort')->get(),
        ]);
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
        $trial->update(['status' => 'provisioned', 'company_id' => $company->id, 'plan_id' => $data['plan_id'], 'handled_at' => now(), 'handled_by' => auth()->id()]);
        AuditLogger::log('saas.trial_provisioned', $company, ['trial_request' => $trial->id]);

        return redirect()->route('admin.companies.show', $company)
            ->with('success', __('Trial company created. Login: :e / temporary password: :p', ['e' => $trial->email, 'p' => $password]));
    }

    // ------------------------------------------------- public website: SEO & contact settings

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
        ]);
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
