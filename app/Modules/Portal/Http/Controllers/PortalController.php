<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\CRM\Models\Complaint;
use App\Modules\CRM\Models\ServiceRating;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Store\Models\OnlinePayment;
use App\Modules\Store\Services\PaymentGateway;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Vehicles\Models\VehicleMake;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\Quotation;
use App\Modules\Workshop\Services\QuotationService;
use App\Support\Export\Exporter;
use App\Support\Tenancy\BranchScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Customer self-service portal. Every query is restricted to the
 * authenticated customer's own records.
 */
class PortalController extends Controller
{
    protected function customer()
    {
        abort_unless(tenant()->company()?->moduleEnabled('portal'), 403, __('The customer portal is not enabled.'));

        return auth()->user()->customer;
    }

    protected function own($model): void
    {
        abort_unless($model->customer_id === $this->customer()->id, 404);
    }

    public function dashboard()
    {
        $c = $this->customer();

        return view('portal::dashboard', [
            'c' => $c,
            'active' => JobCard::query()->withoutGlobalScope(BranchScope::class)->where('customer_id', $c->id)->open()->with('vehicle')->get(),
            'pendingQuotes' => Quotation::query()->withoutGlobalScope(BranchScope::class)->where('customer_id', $c->id)->where('status', 'sent')->get(),
            'balance' => $c->balance(),
            'appointments' => $c->appointments()->withoutGlobalScope(BranchScope::class)->where('starts_at', '>=', now())->whereIn('status', ['scheduled', 'confirmed'])->orderBy('starts_at')->get(),
            'history' => JobCard::query()->withoutGlobalScope(BranchScope::class)->where('customer_id', $c->id)->whereIn('status', ['delivered', 'completed'])->with('vehicle', 'rating')->latest('id')->limit(10)->get(),
        ]);
    }

    public function vehicles()
    {
        $c = $this->customer();

        return view('portal::vehicles', ['vehicles' => $c->vehicles()->with('make', 'vehicleModel')->get(), 'makes' => VehicleMake::query()->orderBy('name')->pluck('name', 'id')]);
    }

    public function storeVehicle(Request $request)
    {
        $c = $this->customer();
        $data = $request->validate(['plate_number' => ['required', 'string', 'max:20'], 'plate_code' => ['nullable', 'string', 'max:10'],
            'vehicle_make_id' => ['nullable', 'exists:vehicle_makes,id'], 'year' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'vin' => ['nullable', 'string', 'size:17'], 'color' => ['nullable', 'string', 'max:40']]);
        Vehicle::create(array_merge($data, ['customer_id' => $c->id, 'plate_code' => strtoupper($data['plate_code'] ?? '')]));

        return back()->with('success', __('Vehicle added.'));
    }

    public function job(JobCard $jobCard)
    {
        $this->own($jobCard);
        $jobCard->load(['vehicle.make', 'vehicle.vehicleModel', 'histories', 'tasks' => fn ($q) => $q->where('approval_status', '!=', 'rejected'),
            'parts.part', 'notes' => fn ($q) => $q->where('visible_to_customer', true), 'quotations', 'invoices', 'inspections.results', 'rating']);
        $photos = $jobCard->attachments()->whereIn('category', ['progress_public', 'check_in'])->get()
            ->merge($jobCard->checkIn?->attachments ?? collect());

        return view('portal::job', ['jc' => $jobCard, 'photos' => $photos]);
    }

    public function rate(Request $request, JobCard $jobCard)
    {
        $this->own($jobCard);
        abort_unless(in_array($jobCard->status, ['delivered', 'completed'], true), 403);
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000']]);
        ServiceRating::query()->updateOrCreate(['job_card_id' => $jobCard->id], $data + ['customer_id' => $jobCard->customer_id]);

        return back()->with('success', __('Thank you for your feedback!'));
    }

    public function quotation(Quotation $quotation)
    {
        $this->own($quotation);

        return view('portal::quotation', ['q' => $quotation->load('items', 'vehicle')]);
    }

    public function respondQuotation(Request $request, Quotation $quotation, QuotationService $service)
    {
        $this->own($quotation);
        $data = $request->validate(['decisions' => ['nullable', 'array'], 'decisions.*' => ['in:approved,rejected'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $service->respond($quotation, $data['decisions'] ?? [], auth()->user()->name, 'portal', $data['notes'] ?? null);

        return back()->with('success', __('Thank you. Your response has been recorded.'));
    }

    public function invoices()
    {
        $c = $this->customer();

        return view('portal::invoices', ['invoices' => Invoice::query()->withoutGlobalScope(BranchScope::class)->where('customer_id', $c->id)
            ->whereIn('status', ['posted', 'partially_paid', 'paid'])->latest('id')->paginate(20), 'gateway' => app(PaymentGateway::class)->enabled()]);
    }

    public function invoicePdf(Invoice $invoice)
    {
        $this->own($invoice);
        abort_unless($invoice->isPosted(), 404);
        $invoice->load('items', 'customer', 'jobCard.vehicle');
        $qr = \App\Support\Codes::qr(route('public.invoice', $invoice->uuid), 3);
        $html = view('sales::print.invoice', ['inv' => $invoice, 'qr' => $qr])->render();

        return response(Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$invoice->number.'.pdf"']);
    }

    public function pay(Invoice $invoice, PaymentGateway $gateway)
    {
        $this->own($invoice);
        abort_unless($gateway->enabled() && $invoice->isPosted() && \App\Support\Money::isPositive($invoice->balanceDue()), 403);
        $p = OnlinePayment::create(['payable_type' => $invoice->getMorphClass(), 'payable_id' => $invoice->id, 'gateway' => $gateway->driver(),
            'amount' => $invoice->balanceDue(), 'status' => 'pending', 'session_id' => Str::random(40), 'reference' => $invoice->number]);

        return redirect()->away($gateway->start($p, route('payments.return', $p), route('portal.invoices'), ['name' => $invoice->customer->name]));
    }

    public function statement(Request $request)
    {
        $c = $this->customer();
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        return view('portal::statement', ['st' => Ledger::partyStatement('customer', $c->id, $from, $to), 'from' => $from, 'to' => $to, 'c' => $c]);
    }

    public function warranties()
    {
        return view('portal::warranties', ['warranties' => $this->customer()->warranties()->with('vehicle', 'claims')->latest('end_date')->get()]);
    }

    public function createAppointment(Request $request, AppointmentService $service)
    {
        $c = $this->customer();
        $date = $request->query('date', now()->addDay()->toDateString());

        return view('portal::appointment', ['vehicles' => $c->vehicles()->get(), 'date' => $date, 'slots' => $service->freeSlots($date, 60),
            'services' => \App\Modules\Workshop\Models\Service::query()->where('is_active', true)->orderBy('name_ar')->get()]);
    }

    public function storeAppointment(Request $request, AppointmentService $service)
    {
        $c = $this->customer();
        $data = $request->validate(['vehicle_id' => ['required', 'integer'], 'date' => ['required', 'date', 'after_or_equal:today'], 'time' => ['required', 'date_format:H:i'],
            'service_id' => ['nullable', 'integer'], 'notes' => ['nullable', 'string', 'max:500']]);
        abort_unless($c->vehicles()->whereKey($data['vehicle_id'])->exists(), 403);
        $a = $service->book(['customer_id' => $c->id, 'vehicle_id' => $data['vehicle_id'], 'service_id' => $data['service_id'] ?? null,
            'starts_at' => $data['date'].' '.$data['time'], 'duration' => 60, 'source' => 'portal', 'notes' => $data['notes'] ?? null]);

        return redirect()->route('portal.dashboard')->with('success', __('Appointment :n booked for :d.', ['n' => $a->number, 'd' => $a->starts_at->format('Y-m-d H:i')]));
    }

    public function complaints()
    {
        $c = $this->customer();

        return view('portal::complaints', ['complaints' => Complaint::query()->where('customer_id', $c->id)->latest()->get(),
            'jobs' => JobCard::query()->withoutGlobalScope(BranchScope::class)->where('customer_id', $c->id)->latest('id')->limit(20)->get()]);
    }

    public function storeComplaint(Request $request)
    {
        $c = $this->customer();
        $data = $request->validate(['subject' => ['required', 'string', 'max:190'], 'description' => ['required', 'string', 'max:3000'], 'job_card_id' => ['nullable', 'integer']]);
        if (! empty($data['job_card_id'])) {
            abort_unless(JobCard::query()->withoutGlobalScope(BranchScope::class)->where('customer_id', $c->id)->whereKey($data['job_card_id'])->exists(), 403);
        }
        Complaint::create($data + ['customer_id' => $c->id, 'status' => 'open', 'priority' => 'normal', 'source' => 'portal']);

        return back()->with('success', __('Your complaint has been submitted. We will contact you soon.'));
    }
}
