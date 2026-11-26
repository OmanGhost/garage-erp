<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Core\Notifications\SystemMessage;
use App\Modules\Saas\Models\DomainRequest;
use App\Modules\Saas\Services\DomainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/** Client side: order a new domain or ask to connect an own domain (Subscription page). */
class DomainRequestController extends Controller
{
    public function store(Request $request)
    {
        abort_unless($request->user()->can('settings.update'), 403);
        $company = tenant()->company()->load('plan');
        abort_unless($company->plan?->allow_custom_domain, 403, __('Your plan does not include a custom domain. Upgrade the plan to request one.'));
        $request->merge(['domain' => DomainService::normalize($request->input('domain'))]);
        $data = $request->validate([
            'type' => ['required', Rule::in(DomainRequest::TYPES)],
            'domain' => ['required', 'string', 'max:190', function ($attr, $value, $fail) {
                if (! DomainService::isValid((string) $value)) {
                    $fail(__('Enter a valid domain name, for example erp.mygarage.om or mygarage.com.'));
                }
            }, Rule::unique('companies', 'custom_domain')],
            'alternatives' => ['nullable', 'string', 'max:255'],
            'years' => ['nullable', 'integer', 'between:1,5'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        if (DomainRequest::query()->where('company_id', $company->id)->whereIn('status', ['new', 'processing'])->exists()) {
            return back()->with('warning', __('You already have a domain request in progress.'));
        }
        $req = DomainRequest::create($data + [
            'company_id' => $company->id, 'requested_by' => auth()->id(), 'status' => 'new', 'years' => $data['years'] ?? 1,
            'price' => $data['type'] === 'register' ? $company->plan?->domain_fee : null,
        ]);
        $admins = User::query()->where('is_super_admin', true)->where('is_active', true)->get();
        Notification::send($admins, new SystemMessage(
            __('Domain request from :c', ['c' => $company->displayName()]), $req->domain.' · '.__(($req->type === 'register' ? 'Register a new domain' : 'Connect my domain')),
            route('admin.domains.index'), 'info'));

        return redirect()->to(route('billing.index').'#domain')->with('success', __('Your domain request was sent. We will contact you shortly.'));
    }

    public function cancel(DomainRequest $domainRequest)
    {
        abort_unless(auth()->user()->can('settings.update') && $domainRequest->company_id === tenant()->id() && $domainRequest->status === 'new', 403);
        $domainRequest->update(['status' => 'cancelled']);

        return back()->with('success', __('Domain request cancelled.'));
    }
}
