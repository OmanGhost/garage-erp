<?php

namespace App\Modules\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Plan;
use App\Modules\Core\Models\SubscriptionInvoice;
use App\Modules\Saas\Models\TrialRequest;
use App\Support\Audit\AuditLogger;
use App\Support\Export\Exporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Super admin: platform clients (tenant companies) — list, filters, client file,
 * contact details, users, subscription invoices, suspension, trial extension,
 * archive / restore. Every change is written to the audit log.
 */
class ClientController extends Controller
{
    public const FILTERS = ['all', 'active', 'trial', 'expiring', 'expired', 'suspended', 'archived'];

    protected function query(Request $request): Builder
    {
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : 'all';
        $q = Company::query()->with('plan')->withCount(['users' => fn ($u) => $u->whereNull('customer_id')]);
        if ($filter === 'archived') {
            $q->onlyTrashed();
        }
        $endExpr = "CASE WHEN subscription_status = 'trial' THEN trial_ends_at ELSE subscription_ends_at END";
        match ($filter) {
            'active' => $q->where('is_active', true)->where('subscription_status', 'active'),
            'trial' => $q->where('subscription_status', 'trial'),
            'expiring' => $q->where('is_active', true)->whereRaw("$endExpr BETWEEN ? AND ?", [now(), now()->addDays(14)]),
            'expired' => $q->where(fn ($w) => $w->where('subscription_status', 'past_due')->orWhereRaw("$endExpr < ?", [now()])),
            'suspended' => $q->where(fn ($w) => $w->where('is_active', false)->orWhereIn('subscription_status', ['suspended', 'cancelled'])),
            default => null,
        };
        if ($t = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('name_ar', 'like', "%$t%")->orWhere('name_en', 'like', "%$t%")
                ->orWhere('email', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")->orWhere('contact_name', 'like', "%$t%")
                ->orWhere('cr_number', 'like', "%$t%")->orWhere('slug', 'like', "%$t%"));
        }
        if ($plan = $request->query('plan')) {
            $q->where('plan_id', $plan);
        }
        $sort = match ($request->query('sort')) {
            'name' => ['name', 'asc'], 'ends' => [DB::raw($endExpr), 'asc'], 'users' => ['users_count', 'desc'], default => ['created_at', 'desc'],
        };

        return $q->orderBy($sort[0], $sort[1]);
    }

    public function index(Request $request)
    {
        tenant()->forget();
        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->export($request);
        }
        $lastLogins = User::query()->whereNull('customer_id')->whereNotNull('company_id')->groupBy('company_id')
            ->selectRaw('company_id, MAX(last_login_at) as last_login')->pluck('last_login', 'company_id');

        return view('saas::clients', [
            'clients' => $this->query($request)->paginate(25)->withQueryString(),
            'plans' => Plan::query()->orderBy('sort')->get(),
            'lastLogins' => $lastLogins,
            'counts' => [
                'all' => Company::query()->count(),
                'active' => Company::query()->where('is_active', true)->where('subscription_status', 'active')->count(),
                'trial' => Company::query()->where('subscription_status', 'trial')->count(),
                'suspended' => Company::query()->where(fn ($w) => $w->where('is_active', false)->orWhereIn('subscription_status', ['suspended', 'cancelled']))->count(),
                'archived' => Company::onlyTrashed()->count(),
                'unpaid' => SubscriptionInvoice::query()->withoutGlobalScopes()->where('status', 'unpaid')->sum('amount'),
            ],
        ]);
    }

    protected function export(Request $request)
    {
        $rows = $this->query($request)->get()->map(fn (Company $c) => [
            $c->id, $c->name, $c->name_en, $c->cr_number, $c->vat_number, $c->email, $c->phone, $c->contact_name, $c->contact_phone, $c->city,
            $c->plan?->name, status_label($c->subscription_status), $c->is_active ? __('Yes') : __('No'),
            optional($c->subscription_status === 'trial' ? $c->trial_ends_at : $c->subscription_ends_at)?->toDateString(),
            $c->users_count, $c->created_at?->toDateString(),
        ])->all();
        $headers = ['ID', __('Name'), __('Name (English)'), __('C.R. number'), __('VAT number'), __('Email'), __('Phone'), __('Contact person'), __('Contact phone'),
            __('City'), __('Plan'), __('Status'), __('Active'), __('Subscription ends'), __('Users'), __('Created At')];
        AuditLogger::log('saas.clients_exported', null, ['count' => count($rows)]);

        return Exporter::download($request->query('export'), __('Clients'), $headers, $rows);
    }

    public function show(int $client)
    {
        tenant()->forget();
        $c = Company::withTrashed()->with('plan', 'subscriptions.plan')->findOrFail($client);
        $users = User::query()->where('company_id', $c->id)->whereNull('customer_id')->orderBy('id')->get();

        return view('saas::client', [
            'c' => $c,
            'plans' => Plan::query()->orderBy('sort')->get(),
            'users' => $users,
            'usage' => [
                'users' => $users->count(),
                'branches' => DB::table('branches')->where('company_id', $c->id)->whereNull('deleted_at')->count(),
                'storage_mb' => round(DB::table('attachments')->where('company_id', $c->id)->sum('size') / 1048576, 1),
                'invoices' => DB::table('invoices')->where('company_id', $c->id)->count(),
                'job_cards' => DB::table('job_cards')->where('company_id', $c->id)->count(),
                'customers' => DB::table('customers')->where('company_id', $c->id)->count(),
            ],
            'invoices' => SubscriptionInvoice::query()->withoutGlobalScopes()->where('company_id', $c->id)->latest()->get(),
            'trial' => TrialRequest::query()->where('company_id', $c->id)->first(),
            'activity' => AuditLog::query()->withoutGlobalScopes()->with('user')
                ->where(fn ($q) => $q->where(fn ($w) => $w->where('auditable_type', $c->getMorphClass())->where('auditable_id', $c->id))
                    ->orWhere(fn ($w) => $w->where('company_id', $c->id)->where('event', 'like', 'saas.%')))
                ->latest('id')->limit(40)->get(),
            'logins' => \App\Modules\Core\Models\LoginHistory::query()->withoutGlobalScopes()->where('company_id', $c->id)->latest('id')->limit(15)->get(),
        ]);
    }

    public function updateProfile(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'], 'name_en' => ['nullable', 'string', 'max:190'], 'legal_name' => ['nullable', 'string', 'max:190'],
            'cr_number' => ['nullable', 'string', 'max:50'], 'vat_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:30'],
            'contact_name' => ['nullable', 'string', 'max:190'], 'contact_phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'], 'address' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(['trial_request', 'direct', 'referral', 'other'])], 'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['phone'] = omani_phone($data['phone'] ?? null);
        $data['contact_phone'] = omani_phone($data['contact_phone'] ?? null);
        $data['name_ar'] = $data['name'];
        $company->update($data);
        AuditLogger::log('saas.client_profile_updated', $company, ['fields' => array_keys($data)]);

        return back()->with('success', __('Saved successfully.'));
    }

    /** Suspend (blocks sign-in) or reactivate a client. */
    public function toggle(Company $company)
    {
        $company->is_active = ! $company->is_active;
        if ($company->is_active && $company->subscription_status === 'suspended') {
            $company->subscription_status = $company->subscription_ends_at?->isFuture() ? 'active' : 'past_due';
        }
        $company->save();
        AuditLogger::log($company->is_active ? 'saas.client_activated' : 'saas.client_suspended', $company);

        return back()->with('success', $company->is_active ? __('Client activated.') : __('Client suspended. Its users can no longer sign in.'));
    }

    public function extendTrial(Request $request, Company $company)
    {
        $days = (int) $request->validate(['days' => ['required', 'integer', 'min:1', 'max:90']])['days'];
        $from = $company->trial_ends_at && $company->trial_ends_at->isFuture() ? $company->trial_ends_at : now();
        $company->update(['trial_ends_at' => $from->copy()->addDays($days), 'subscription_status' => 'trial', 'is_active' => true]);
        AuditLogger::log('saas.trial_extended', $company, ['days' => $days, 'until' => $company->trial_ends_at->toDateString()]);

        return back()->with('success', __('Trial extended until :d.', ['d' => $company->trial_ends_at->format('Y-m-d')]));
    }

    /** Generate a new temporary password for one of the client's users (shown once). */
    public function resetPassword(Company $company, User $user)
    {
        abort_unless($user->company_id === $company->id && ! $user->is_super_admin, 404);
        $password = Str::password(12, symbols: false);
        $user->forceFill(['password' => $password, 'is_active' => true])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete(); // sign the user out everywhere
        AuditLogger::log('saas.client_user_password_reset', $company, ['user' => $user->email]);

        return back()->with('success', __('New temporary password for :e: :p', ['e' => $user->email, 'p' => $password]));
    }

    public function toggleUser(Company $company, User $user)
    {
        abort_unless($user->company_id === $company->id && ! $user->is_super_admin, 404);
        $user->forceFill(['is_active' => ! $user->is_active])->save();
        if (! $user->is_active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        AuditLogger::log($user->is_active ? 'saas.client_user_enabled' : 'saas.client_user_disabled', $company, ['user' => $user->email]);

        return back()->with('success', __('Saved successfully.'));
    }

    public function markInvoicePaid(Company $company, int $invoice)
    {
        $inv = SubscriptionInvoice::query()->withoutGlobalScopes()->where('company_id', $company->id)->findOrFail($invoice);
        if ($inv->status === 'unpaid') {
            app(\App\Modules\Saas\Services\BillingService::class)->markPaid($inv, null, null);
        }

        return back()->with('success', __('Payment recorded.'));
    }

    /** Archive (soft delete): data is kept and can be restored; users cannot sign in. */
    public function archive(Request $request, Company $company)
    {
        $request->validate(['confirm_slug' => ['required', Rule::in([$company->slug])]], ['confirm_slug.in' => __('Type the client code exactly to confirm.')]);
        $company->forceFill(['is_active' => false])->save();
        $company->delete();
        AuditLogger::log('saas.client_archived', $company);

        return redirect()->route('admin.clients.index')->with('success', __('Client archived. It can be restored from the "Archived" filter.'));
    }

    public function restore(int $client)
    {
        $company = Company::onlyTrashed()->findOrFail($client);
        $company->restore();
        $company->forceFill(['is_active' => true])->save();
        AuditLogger::log('saas.client_restored', $company);

        return redirect()->route('admin.clients.show', $company->id)->with('success', __('Client restored.'));
    }
}
