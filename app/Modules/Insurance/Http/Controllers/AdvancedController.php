<?php

namespace App\Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Fleet;
use App\Modules\Fleet\Models\FleetApproval;
use App\Modules\Fleet\Services\FleetService;
use App\Modules\Insurance\Models\InsuranceClaim;
use App\Modules\Insurance\Services\InsuranceService;
use App\Modules\Warranty\Models\Warranty;
use App\Modules\Warranty\Models\WarrantyClaim;
use App\Modules\Warranty\Services\WarrantyService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdvancedController extends Controller
{
    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    // ------------------------------------------------------------ insurance

    public function claim(InsuranceClaim $claim)
    {
        $this->can('insurance_claims.view');

        return view('insurance::claim', ['c' => $claim->load('insuranceCompany', 'customer', 'vehicle', 'jobCard', 'invoices')]);
    }

    public function claimStatus(Request $request, InsuranceClaim $claim, InsuranceService $service)
    {
        $this->can('insurance_claims.update');
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', InsuranceService::STATUSES)], 'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'deductible_amount' => ['nullable', 'numeric', 'min:0'], 'claim_number' => ['nullable', 'string', 'max:60'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $service->setStatus($claim, $data['status'], array_filter($data, fn ($v) => $v !== null));

        return back()->with('success', __('Claim updated.'));
    }

    public function claimInvoice(InsuranceClaim $claim, InsuranceService $service)
    {
        $this->can('invoices.create');
        $r = $service->invoice($claim);

        return redirect()->route('invoices.show', $r['insurer'])->with('success', __('Insurer invoice drafted').($r['customer'] ? ' + '.__('deductible invoice for the customer') : '').'.');
    }

    // ---------------------------------------------------------------- fleet

    public function fleet(Request $request, Fleet $fleet, FleetService $service)
    {
        $this->can('fleets.view');
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        return view('insurance::fleet', ['f' => $fleet->load('customer', 'approvals.jobCard'), 'costs' => $service->vehicleCosts($fleet, $from, $to),
            'monthSpend' => $service->monthSpend($fleet), 'from' => $from, 'to' => $to,
            'pendingJobs' => $fleet->jobCards()->whereNull('invoice_id')->whereIn('status', ['ready_for_delivery', 'delivered', 'completed'])->with('vehicle')->get()]);
    }

    public function fleetInvoice(Request $request, Fleet $fleet, FleetService $service)
    {
        $this->can('invoices.create');
        $month = Carbon::createFromFormat('Y-m', $request->validate(['month' => ['required', 'date_format:Y-m']])['month']);
        $inv = $service->monthlyInvoice($fleet, $month);

        return redirect()->route('invoices.show', $inv)->with('success', __('Consolidated monthly invoice posted.'));
    }

    public function decideApproval(Request $request, FleetApproval $approval, string $decision, FleetService $service)
    {
        $this->can('fleets.approve');
        $service->decide($approval, $decision === 'approve', $request->input('name', auth()->user()->name));

        return back()->with('success', __('Decision recorded.'));
    }

    // ------------------------------------------------------------- warranty

    public function createWarrantyClaim(Request $request)
    {
        $this->can('warranty_claims.create');
        $w = Warranty::query()->with('customer', 'vehicle', 'part', 'invoice')->findOrFail($request->query('warranty_id'));

        return view('insurance::warranty-claim-form', ['w' => $w]);
    }

    public function storeWarrantyClaim(Request $request, WarrantyService $service)
    {
        $this->can('warranty_claims.create');
        $data = $request->validate(['warranty_id' => ['required', 'integer'], 'odometer' => ['nullable', 'integer', 'min:0'], 'description' => ['required', 'string', 'max:2000'],
            'supplier_claim' => ['nullable', 'boolean']]);
        $w = Warranty::query()->findOrFail($data['warranty_id']);
        $claim = $service->openClaim($w, ['odometer' => $data['odometer'] ?? null, 'description' => $data['description'], 'supplier_claim' => $request->boolean('supplier_claim')]);

        return redirect()->route('warranty.claims.show', $claim)->with('success', __('Warranty claim opened.'));
    }

    public function warrantyClaim(WarrantyClaim $claim)
    {
        $this->can('warranty_claims.view');

        return view('insurance::warranty-claim', ['c' => $claim->load('warranty.customer', 'warranty.vehicle', 'warranty.part', 'warranty.invoice', 'warranty.supplier', 'reworkJobCard', 'events.user', 'creator'),
            'label' => self::eventLabel(...)]);
    }

    public static function eventLabel(\App\Modules\Warranty\Models\WarrantyClaimEvent $e): string
    {
        return match ($e->event) {
            'opened' => __('Claim opened'),
            'status' => __('Status changed from :a to :b', ['a' => status_label((string) $e->from_status), 'b' => status_label((string) $e->to_status)]),
            'supplier' => __('Supplier claim: :s', ['s' => status_label((string) $e->to_status)]),
            default => __('Note'),
        };
    }

    public function warrantyNote(Request $request, WarrantyClaim $claim)
    {
        $this->can('warranty_claims.update');
        $claim->logEvent('note', null, null, $request->validate(['note' => ['required', 'string', 'max:1000']])['note']);

        return back()->with('success', __('Note added.'));
    }

    public function printWarrantyClaim(WarrantyClaim $claim, string $format = 'a4')
    {
        $this->can('warranty_claims.print');
        $claim->load('warranty.customer', 'warranty.vehicle', 'warranty.part', 'warranty.invoice', 'warranty.supplier', 'reworkJobCard', 'events.user', 'creator');
        $html = view('insurance::print.warranty-claim', ['c' => $claim, 'labels' => self::eventLabel(...)])->render();

        return $format === 'pdf'
            ? response(\App\Support\Export\Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$claim->number.'.pdf"'])
            : response($html);
    }

    public function printWarranty(Warranty $warranty, string $format = 'a4')
    {
        $this->can('warranties.print');
        $warranty->load('customer', 'vehicle', 'part.brand', 'invoice', 'claims');
        $html = view('insurance::print.warranty-card', ['w' => $warranty])->render();

        return $format === 'pdf'
            ? response(\App\Support\Export\Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="warranty-'.$warranty->id.'.pdf"'])
            : response($html);
    }

    public function warrantyAction(Request $request, WarrantyClaim $claim, string $action, WarrantyService $service)
    {
        $this->can('warranty_claims.approve');
        switch ($action) {
            case 'approve':
                $data = $request->validate(['resolution' => ['required', 'in:replace,repair,refund'], 'inspection_result' => ['nullable', 'string', 'max:2000']]);
                $jc = $service->approveWithRework($claim, $data);

                return redirect()->route('job-cards.show', $jc)->with('success', __('Claim approved. Rework job card created (no charge).'));
            case 'reject':
                $claim->update(['status' => 'rejected', 'resolution' => 'reject', 'inspection_result' => $request->input('inspection_result')]);
                break;
            case 'complete':
                $service->complete($claim);
                break;
            case 'supplier':
                $data = $request->validate(['supplier_claim_status' => ['required', 'in:submitted,accepted,rejected,settled'], 'supplier_recovered_amount' => ['nullable', 'numeric', 'min:0']]);
                $claim->update(['supplier_claim' => true] + $data);
                break;
        }

        return back()->with('success', __('Claim updated.'));
    }
}
