<?php

namespace App\Modules\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Company;
use App\Modules\Saas\Models\DomainRequest;
use App\Modules\Saas\Services\DomainService;
use App\Support\Audit\AuditLogger;
use App\Support\Mail\MailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * Platform admin: client custom domains (assign, verify DNS, activate / disable), clients' domain requests,
 * and the platform SMTP server.
 */
class DomainController extends Controller
{
    public function __construct(protected DomainService $domains) {}

    public function index(Request $request)
    {
        tenant()->forget();

        return view('saas::domains', [
            'companies' => Company::query()->with('plan')->whereNotNull('custom_domain')->orderBy('name')->get(),
            'requests' => DomainRequest::query()->with('company.plan', 'requester', 'handler')
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->whereIn('status', ['new', 'processing']))
                ->latest()->get(),
            'clients' => Company::query()->orderBy('name')->pluck('name', 'id'),
            'platformHost' => DomainService::platformHost(),
            'smtp' => MailService::platformConfig() ?? [],
        ]);
    }

    /** Assign (or remove) a custom domain to a client. */
    public function assign(Request $request)
    {
        $request->merge(['domain' => DomainService::normalize($request->input('domain'))]);
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'domain' => ['nullable', 'string', 'max:190', function ($a, $v, $fail) {
                if ($v && ! DomainService::isValid($v)) {
                    $fail(__('Enter a valid domain name, for example erp.mygarage.om or mygarage.com.'));
                }
            }],
        ]);
        $company = Company::query()->findOrFail($data['company_id']);
        $request->validate(['domain' => [Rule::unique('companies', 'custom_domain')->ignore($company->id)]]);
        $this->setDomain($company, $data['domain'] ?? null);

        return back()->with('success', $company->custom_domain
            ? __('Domain :d assigned to :c. Ask the client to add the DNS records, then verify.', ['d' => $company->custom_domain, 'c' => $company->name])
            : __('Custom domain removed from :c.', ['c' => $company->name]));
    }

    public function verify(Company $company)
    {
        $r = $this->domains->verify($company);
        Cache::forget('domain-host:'.$company->custom_domain);

        return back()->with($r['ok'] ? 'success' : 'warning', $r['message'].($r['found'] ? ' ('.__('Found').': '.implode(', ', $r['found']).')' : ''));
    }

    public function status(Request $request, Company $company)
    {
        $status = $request->validate(['status' => ['required', Rule::in(['active', 'disabled', 'pending'])]])['status'];
        $company->forceFill(['domain_status' => $status, 'domain_verified_at' => $status === 'active' ? ($company->domain_verified_at ?? now()) : $company->domain_verified_at])->save();
        Cache::forget('domain-host:'.$company->custom_domain);
        AuditLogger::log('saas.domain_status', $company, ['domain' => $company->custom_domain, 'status' => $status]);

        return back()->with('success', __('Domain :d is now :s.', ['d' => $company->custom_domain, 's' => status_label($status)]));
    }

    /** Handle a client's request: processing / completed (assigns the domain) / rejected. */
    public function handle(Request $request, DomainRequest $domainRequest)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['processing', 'completed', 'rejected'])],
            'price' => ['nullable', 'numeric', 'min:0'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['status'] === 'completed') {
            $taken = Company::query()->where('custom_domain', $domainRequest->domain)->where('id', '!=', $domainRequest->company_id)->exists();
            if ($taken) {
                return back()->with('error', __('The domain :d is already used by another client.', ['d' => $domainRequest->domain]));
            }
            $this->setDomain($domainRequest->company, $domainRequest->domain);
        }
        $domainRequest->update($data + ['handled_by' => auth()->id(), 'handled_at' => now()]);
        AuditLogger::log('saas.domain_request', $domainRequest->company, ['domain' => $domainRequest->domain, 'status' => $data['status']]);

        return back()->with('success', __('Domain request :d: :s.', ['d' => $domainRequest->domain, 's' => status_label($data['status'])]));
    }

    public function saveSmtp(Request $request)
    {
        if ($request->boolean('disable')) {
            MailService::savePlatformConfig(null);

            return redirect()->to(route('admin.domains.index').'#smtp')->with('success', __('Platform SMTP disabled. The .env mail settings are used.'));
        }
        $old = MailService::platformConfig() ?? [];
        $data = $request->validate(MailService::rules());
        $data['password'] = filled($data['password'] ?? null) ? $data['password'] : ($old['password'] ?? null);
        MailService::savePlatformConfig(array_intersect_key($data, array_flip(MailService::FIELDS)));
        AuditLogger::log('saas.smtp_updated', null, ['host' => $data['host']]);

        return redirect()->to(route('admin.domains.index').'#smtp')->with('success', __('Platform SMTP saved.'));
    }

    public function testSmtp(Request $request)
    {
        $to = $request->validate(['test_to' => ['required', 'email']])['test_to'];
        $c = MailService::platformConfig();
        if (! $c) {
            return redirect()->to(route('admin.domains.index').'#smtp')->with('error', __('Save the SMTP settings first.'));
        }
        $r = MailService::test($c, $to);

        return redirect()->to(route('admin.domains.index').'#smtp')->with($r['ok'] ? 'success' : 'error', $r['message']);
    }

    protected function setDomain(Company $company, ?string $domain): void
    {
        if ($company->custom_domain) {
            Cache::forget('domain-host:'.$company->custom_domain);
        }
        $company->forceFill([
            'custom_domain' => $domain, 'domain_status' => $domain ? 'pending' : null, 'domain_verified_at' => null,
        ])->save();
        if ($domain) {
            $this->domains->ensureToken($company);
        }
        AuditLogger::log('saas.domain_assigned', $company, ['domain' => $domain]);
    }
}
