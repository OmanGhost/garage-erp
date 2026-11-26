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

    /* ---------------- interactive inspection report (DVI) ---------------- */

    protected function findInspection(string $token): \App\Modules\Workshop\Models\Inspection
    {
        abort_unless(strlen($token) >= 40, 404);
        $i = \App\Modules\Workshop\Models\Inspection::query()->withoutGlobalScopes()->where('public_token', $token)->firstOrFail();
        $this->enterTenant($i->company_id);

        return \App\Modules\Workshop\Models\Inspection::query()->findOrFail($i->id);
    }

    public function dvi(string $token)
    {
        $i = $this->findInspection($token);
        if (! $i->customer_viewed_at) {
            $i->forceFill(['customer_viewed_at' => now()])->save();
        }
        $i->load('jobCard.vehicle.make', 'jobCard.vehicle.vehicleModel', 'jobCard.customer', 'results', 'findings.service', 'findings.part', 'findings.attachments', 'attachments', 'technician');

        return view('workshop::public.dvi', ['i' => $i, 'company' => tenant()->company(), 'token' => $token]);
    }

    public function dviDecide(Request $request, string $token, int $finding)
    {
        $i = $this->findInspection($token);
        $f = $i->findings()->findOrFail($finding);
        app(\App\Modules\Workshop\Services\InspectionService::class)->decide($f, (string) $request->validate(['decision' => ['required', 'in:approved,declined']])['decision']);

        return redirect()->to(route('public.dvi', $token).'#f'.$f->id)->with('success', __('Thank you, your choice was saved.'));
    }

    public function dviSign(Request $request, string $token)
    {
        $i = $this->findInspection($token);
        $data = $request->validate(['signature_file' => ['required', 'file', 'mimetypes:image/png', 'max:1024'], 'name' => ['required', 'string', 'max:190']]);
        app(\App\Modules\Workshop\Services\InspectionService::class)->sign($i, 'customer', (string) file_get_contents($request->file('signature_file')->getRealPath()), $data['name']);

        return redirect()->route('public.dvi', $token)->with('success', __('Thank you, the report was signed.'));
    }

    /** Photos of the report: only attachments of this inspection or of its findings. */
    public function dviPhoto(string $token, int $attachment)
    {
        $i = $this->findInspection($token);
        $a = \App\Modules\Core\Models\Attachment::query()->whereKey($attachment)->where('mime', 'like', 'image/%')
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('attachable_type', $i->getMorphClass())->where('attachable_id', $i->id))
                ->orWhere(fn ($w) => $w->where('attachable_type', (new \App\Modules\Workshop\Models\InspectionFinding)->getMorphClass())->whereIn('attachable_id', $i->findings()->pluck('id'))))
            ->firstOrFail();
        abort_unless(\Illuminate\Support\Facades\Storage::disk($a->disk)->exists($a->path), 404);

        return \Illuminate\Support\Facades\Storage::disk($a->disk)->response($a->path, $a->original_name, ['Content-Type' => $a->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=86400']);
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
