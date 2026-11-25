<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\FleetApproval;
use App\Modules\Fleet\Services\FleetService;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Workshop\Models\Quotation;
use App\Modules\Workshop\Models\VehicleCheckIn;
use App\Modules\Workshop\Services\QuotationService;
use App\Support\Tenancy\BranchScope;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Http\Request;

/**
 * Customer-facing pages reachable by unguessable tokens (no login):
 * quotation approval, fleet approval, job tracking, invoice verification.
 */
class PublicController extends Controller
{
    protected function enterTenant(int $companyId): void
    {
        tenant()->set($companyId);
        app()->setLocale(session('locale', tenant()->company()?->locale ?? 'ar'));
    }

    protected function findQuotation(string $token): Quotation
    {
        abort_unless(strlen($token) >= 40, 404);
        $q = Quotation::query()->withoutGlobalScopes([CompanyScope::class, BranchScope::class])->where('access_token', $token)->firstOrFail();
        $this->enterTenant($q->company_id);

        return $q;
    }

    public function quotation(string $token)
    {
        $q = $this->findQuotation($token);
        $q->load('items', 'customer', 'vehicle.make', 'vehicle.vehicleModel');

        return view('workshop::public.quotation', ['q' => $q, 'company' => tenant()->company()]);
    }

    public function respondQuotation(Request $request, string $token, QuotationService $service)
    {
        $q = $this->findQuotation($token);
        $data = $request->validate([
            'decisions' => ['nullable', 'array'], 'decisions.*' => ['in:approved,rejected'],
            'name' => ['required', 'string', 'max:190'], 'notes' => ['nullable', 'string', 'max:2000'], 'accept_terms' => ['accepted'],
        ]);
        $service->respond($q, $data['decisions'] ?? [], $data['name'], 'portal', $data['notes'] ?? null);

        return back()->with('success', __('Thank you. Your response has been recorded.'));
    }

    public function fleetApproval(string $token)
    {
        $a = FleetApproval::query()->withoutGlobalScopes()->where('token', $token)->firstOrFail();
        $this->enterTenant($a->company_id);
        $a->load('fleet', 'jobCard.vehicle', 'jobCard.tasks', 'jobCard.parts');

        return view('workshop::public.fleet-approval', ['a' => $a, 'company' => tenant()->company()]);
    }

    public function fleetDecide(Request $request, string $token, FleetService $fleets)
    {
        $a = FleetApproval::query()->withoutGlobalScopes()->where('token', $token)->firstOrFail();
        $this->enterTenant($a->company_id);
        abort_unless($a->status === 'pending', 410);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'name' => ['required', 'string', 'max:190'], 'notes' => ['nullable', 'string', 'max:500']]);
        $fleets->decide($a, $data['decision'] === 'approve', $data['name'], $data['notes'] ?? null);

        return back()->with('success', __('Thank you. Your decision has been recorded.'));
    }

    public function track(string $token)
    {
        $ci = VehicleCheckIn::query()->withoutGlobalScopes()->where('receipt_token', $token)->firstOrFail();
        $this->enterTenant($ci->company_id);
        $jc = $ci->jobCard()->withoutGlobalScope(BranchScope::class)->with(['vehicle.make', 'vehicle.vehicleModel', 'histories',
            'notes' => fn ($q) => $q->where('visible_to_customer', true), 'quotations' => fn ($q) => $q->whereIn('status', ['sent', 'approved', 'partially_approved'])])->first();

        return view('workshop::public.track', ['jc' => $jc, 'ci' => $ci, 'company' => tenant()->company()]);
    }

    public function receipt(string $token)
    {
        return $this->track($token);
    }

    /** Invoice authenticity check (QR on printed tax invoices). */
    public function invoice(string $uuid)
    {
        $inv = Invoice::query()->withoutGlobalScopes()->where('uuid', $uuid)->whereNotNull('number')->firstOrFail();
        $this->enterTenant($inv->company_id);

        return view('workshop::public.invoice', ['inv' => $inv->load('customer'), 'company' => tenant()->company()]);
    }
}
