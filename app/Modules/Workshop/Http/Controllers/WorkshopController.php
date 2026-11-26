<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Services\FileStorage;
use App\Modules\Core\Services\NotificationService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Vehicles\Models\VehicleMake;
use App\Modules\Workshop\Http\Requests\CheckInRequest;
use App\Modules\Workshop\Http\Requests\QuotationRequest;
use App\Modules\Workshop\Models\Department;
use App\Modules\Workshop\Models\Inspection;
use App\Modules\Workshop\Models\InspectionFinding;
use App\Modules\Workshop\Models\InspectionTemplate;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\JobCardPart;
use App\Modules\Workshop\Models\JobCardTask;
use App\Modules\Workshop\Models\Quotation;
use App\Modules\Workshop\Services\InspectionService;
use App\Modules\Workshop\Services\JobCardService;
use App\Modules\Workshop\Services\JobCardWorkflow;
use App\Modules\Workshop\Services\QuotationService;
use App\Modules\Workshop\Services\ReceptionService;
use App\Support\Export\Exporter;
use App\Support\Options;
use Illuminate\Http\Request;

class WorkshopController extends Controller
{
    public function __construct(
        protected JobCardService $jobs,
        protected QuotationService $quotes,
        protected InspectionService $inspections,
    ) {}

    protected function can(string $perm): void
    {
        abort_unless(auth()->user()->can($perm), 403);
    }

    // ------------------------------------------------------------- board

    public function board()
    {
        $this->can('job_cards.view');

        return view('workshop::board');
    }

    // --------------------------------------------------------- reception

    public function reception(Request $request)
    {
        $this->can('reception.create');
        $customer = $request->query('customer_id') ? Customer::query()->find($request->query('customer_id')) : null;
        $appointment = $request->query('appointment_id') ? \App\Modules\Appointments\Models\Appointment::query()->with('customer', 'vehicle')->find($request->query('appointment_id')) : null;

        // after a failed submit, show the customer / vehicle / model that were already chosen (not only their hidden ids)
        $oldCustomer = old('customer_id') ? Customer::query()->find(old('customer_id')) : null;
        $oldVehicle = old('vehicle_id') ? \App\Modules\Vehicles\Models\Vehicle::query()->with('make', 'vehicleModel')->find(old('vehicle_id')) : null;
        $oldModel = old('vehicle_model_id') ? \App\Modules\Vehicles\Models\VehicleModel::query()->find(old('vehicle_model_id')) : null;

        return view('workshop::reception', [
            'makes' => VehicleMake::query()->orderBy('name')->get(),
            'departments' => Options::departments(), 'advisors' => Options::users(),
            'customer' => $oldCustomer ?? $customer ?? $appointment?->customer, 'appointment' => $appointment,
            'vehicle' => $oldVehicle ?? $appointment?->vehicle, 'vehicleModel' => $oldModel,
        ]);
    }

    public function checkIn(CheckInRequest $request, ReceptionService $reception)
    {
        $jc = $reception->checkIn($request->validated(), $request->file('photos', []));
        if ($request->boolean('notify')) {
            app(NotificationService::class)->notifyCustomer($jc->customer, 'job_card.received', [
                'number' => $jc->number, 'plate' => $jc->vehicle->plate(), 'link' => route('public.track', $jc->checkIn->receipt_token),
            ], [$jc->customer->preferred_channel ?: 'sms'], $jc);
        }

        return redirect()->route('job-cards.show', $jc)->with('success', __('Vehicle received. Job card :n created.', ['n' => $jc->number]))
            ->with('print_receipt', route('reception.receipt', $jc));
    }

    public function receipt(JobCard $jobCard)
    {
        $this->can('job_cards.print');
        $jobCard->load('checkIn.attachments', 'customer', 'vehicle.make', 'vehicle.vehicleModel', 'advisor');

        return view('workshop::print.receipt', ['jc' => $jobCard]);
    }

    // ------------------------------------------------------------ job card

    public function show(JobCard $jobCard)
    {
        $this->can('job_cards.view');
        $jobCard->load([
            'customer', 'vehicle.make', 'vehicle.vehicleModel', 'department', 'advisor', 'bay', 'fleet', 'insuranceClaim',
            'tasks.technician', 'tasks.department', 'tasks.service', 'parts.part', 'parts.warehouse', 'parts.issuedTo', 'checkIn.receiver',
            'inspections.technician', 'quotations', 'histories.user', 'notes.user', 'qualityChecks.inspector', 'invoices',
        ]);

        return view('workshop::job-card', [
            'jc' => $jobCard,
            'next' => JobCardWorkflow::next($jobCard->status),
            'guide' => app(\App\Modules\Workshop\Services\JobCardGuide::class)->build($jobCard),
            'departments' => Options::departments(), 'technicians' => Options::technicians(), 'warehouses' => Options::warehouses(),
            'bays' => \App\Modules\Workshop\Models\WorkshopBay::query()->where('is_active', true)->pluck('name', 'id'),
            'templates' => InspectionTemplate::query()->where('is_active', true)->pluck('name', 'id'),
            'users' => Options::users(),
        ]);
    }

    public function print(JobCard $jobCard)
    {
        $this->can('job_cards.print');
        $jobCard->load('customer', 'vehicle.make', 'vehicle.vehicleModel', 'tasks.technician', 'parts.part', 'checkIn', 'advisor', 'department');

        return view('workshop::print.job-card', ['jc' => $jobCard]);
    }

    public function status(Request $request, JobCard $jobCard)
    {
        $this->can('job_cards.change_status');
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', JobCard::STATUSES)], 'notes' => ['nullable', 'string', 'max:250']]);
        $this->jobs->changeStatus($jobCard, $data['status'], $data['notes'] ?? null);

        return back()->with('success', __('Status updated to :s.', ['s' => status_label($data['status'])]));
    }

    public function update(Request $request, JobCard $jobCard)
    {
        $this->can('job_cards.update');
        abort_if($jobCard->isClosed(), 403);
        $data = $request->validate([
            'priority' => ['required', 'in:low,normal,high,urgent'], 'department_id' => ['nullable', 'integer'],
            'bay_id' => ['nullable', 'integer'], 'service_advisor_id' => ['nullable', 'integer'], 'promised_at' => ['nullable', 'date'],
            'customer_complaint' => ['nullable', 'string', 'max:5000'], 'diagnosis' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'], 'odometer_out' => ['nullable', 'integer', 'min:0'],
        ]);
        $jobCard->update($data);

        return back()->with('success', __('Saved successfully.'));
    }

    public function addTask(Request $request, JobCard $jobCard)
    {
        $this->can('job_cards.update');
        $data = $request->validate([
            'service_id' => ['nullable', 'integer'], 'description' => ['required_without:service_id', 'nullable', 'string', 'max:250'],
            'department_id' => ['nullable', 'integer'], 'technician_id' => ['nullable', 'integer'],
            'qty' => ['nullable', 'numeric', 'gt:0'], 'unit_price' => ['nullable', 'numeric', 'min:0'], 'discount' => ['nullable', 'numeric', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'], 'pre_approved' => ['nullable', 'boolean'],
        ]);
        $task = $this->jobs->addTask($jobCard, array_filter($data, fn ($v) => $v !== null && $v !== ''));

        return back()->with($task->approval_status === 'pending' ? 'warning' : 'success', $task->approval_status === 'pending'
            ? __('Additional task added. It requires customer approval before work can start.') : __('Task added.'));
    }

    public function taskAction(Request $request, JobCardTask $task, string $action)
    {
        $jc = JobCard::query()->findOrFail($task->job_card_id); // tenant check
        $isOwn = $task->technician_id === auth()->id();
        abort_unless(auth()->user()->can('job_cards.update') || ($isOwn && auth()->user()->can('job_cards.change_status')), 403);
        match ($action) {
            'start' => $this->jobs->startTask($task, $request->integer('technician_id') ?: null),
            'pause' => $this->jobs->pauseTask($task),
            'finish' => $this->jobs->finishTask($task),
            'cancel' => $this->jobs->cancelTask($task),
            'assign' => $this->jobs->updateTask($task, $request->validate(['technician_id' => ['nullable', 'integer'], 'department_id' => ['nullable', 'integer']])),
        };

        return back()->with('success', __('Task updated.'));
    }

    public function addPart(Request $request, JobCard $jobCard)
    {
        $this->can('job_cards.update');
        $data = $request->validate([
            'part_id' => ['required', 'integer'], 'qty' => ['required', 'numeric', 'gt:0'], 'warehouse_id' => ['nullable', 'integer'],
            'unit_price' => ['nullable', 'numeric', 'min:0'], 'discount' => ['nullable', 'numeric', 'min:0'], 'pre_approved' => ['nullable', 'boolean'],
        ]);
        if (! empty($data['warehouse_id'])) {
            Warehouse::query()->findOrFail($data['warehouse_id']);
        }
        $p = $this->jobs->requestPart($jobCard, array_filter($data, fn ($v) => $v !== null && $v !== ''));
        $msg = $p->status === 'reserved' ? __('Part reserved from stock.') : __('Part requested. Not enough stock was available to reserve the full quantity.');

        return back()->with($p->status === 'reserved' ? 'success' : 'warning', $msg);
    }

    public function partAction(Request $request, JobCardPart $part, string $action)
    {
        JobCard::query()->findOrFail($part->job_card_id); // tenant check
        abort_unless(auth()->user()->can($action === 'cancel' ? 'job_cards.update' : 'stock.issue'), 403);
        match ($action) {
            'issue' => $this->jobs->issuePart($part, $request->input('qty'), $request->integer('technician_id') ?: null),
            'return' => $this->jobs->returnPart($part, $request->validate(['qty' => ['required', 'numeric', 'gt:0']])['qty']),
            'cancel' => $this->jobs->cancelPart($part),
        };

        return back()->with('success', __('Part updated.'));
    }

    public function addNote(Request $request, JobCard $jobCard)
    {
        $this->can('job_cards.view');
        $data = $request->validate(['note' => ['required', 'string', 'max:5000'], 'visible_to_customer' => ['nullable', 'boolean'],
            'photos' => ['nullable', 'array', 'max:10'], 'photos.*' => ['file', 'max:'.config('erp.uploads.max_kb'), 'mimes:'.config('erp.uploads.mimes')]]);
        $note = $jobCard->notes()->create(['user_id' => auth()->id(), 'note' => $data['note'], 'visible_to_customer' => $request->boolean('visible_to_customer')]);
        foreach ($request->file('photos', []) as $f) {
            FileStorage::attach($jobCard, $f, $request->boolean('visible_to_customer') ? 'progress_public' : 'progress', mb_substr($data['note'], 0, 150));
        }

        return back()->with('success', __('Note added.'));
    }

    public function qualityCheck(Request $request, JobCard $jobCard)
    {
        $this->can('quality_checks.create');
        $data = $request->validate(['result' => ['required', 'in:pass,fail'], 'notes' => ['nullable', 'string', 'max:2000'], 'checklist' => ['nullable', 'array']]);
        $this->jobs->qualityCheck($jobCard, $data['result'], $data['checklist'] ?? [], $data['notes'] ?? null);

        return back()->with('success', __('Quality inspection recorded.'));
    }

    public function decideAdditional(Request $request, JobCard $jobCard)
    {
        $this->can('job_cards.approve_additional');
        $data = $request->validate([
            'tasks' => ['nullable', 'array'], 'tasks.*' => ['in:approved,rejected'],
            'parts' => ['nullable', 'array'], 'parts.*' => ['in:approved,rejected'],
            'approved_by' => ['required', 'string', 'max:190'], 'channel' => ['required', 'in:in_person,phone,whatsapp,email,portal'],
        ]);
        $this->jobs->decideAdditional($jobCard, $data['tasks'] ?? [], $data['parts'] ?? [], $data['approved_by'], $data['channel']);

        return back()->with('success', __('Customer decision recorded.'));
    }

    public function additionalQuote(JobCard $jobCard)
    {
        $this->can('quotations.create');
        $q = $this->quotes->forAdditionalWork($jobCard);
        $this->quotes->send($q);

        return redirect()->route('quotations.show', $q)->with('success', __('Supplementary quotation sent to the customer.'));
    }

    public function invoice(JobCard $jobCard, InvoiceService $invoices)
    {
        $this->can('invoices.create');
        $inv = $invoices->fromJobCard($jobCard);

        return redirect()->route('invoices.show', $inv)->with('success', __('Draft invoice prepared. Review and post it.'));
    }

    public function purchaseRequest(JobCard $jobCard, PurchasingService $purchasing)
    {
        $this->can('purchase_requests.create');
        $items = $jobCard->parts()->with('part')->where('status', 'requested')->get()
            ->map(fn ($p) => ['part_id' => $p->part_id, 'qty' => \App\Support\Money::sub($p->qty, $p->qty_reserved), 'description' => $p->description])
            ->filter(fn ($i) => \App\Support\Money::isPositive($i['qty']))->values()->all();
        abort_if(! $items, 422, __('No missing parts to request.'));
        $pr = $purchasing->createRequest(['job_card_id' => $jobCard->id, 'priority' => $jobCard->priority === 'urgent' ? 'urgent' : 'high',
            'notes' => __('Parts for job card').' '.$jobCard->number], $items);
        if (in_array($jobCard->status, [JobCard::APPROVED, JobCard::IN_PROGRESS], true)) {
            $this->jobs->changeStatus($jobCard, JobCard::AWAITING_PARTS, $pr->number, true);
        }

        return redirect()->route('purchase-requests.show', $pr)->with('success', __('Purchase request created.'));
    }

    // ---------------------------------------------------------- inspection

    public function startInspection(Request $request, JobCard $jobCard)
    {
        $this->can('inspections.create');
        $i = $this->inspections->start($jobCard, $request->integer('template_id') ?: null, $request->integer('technician_id') ?: null);

        return redirect()->route('inspections.show', $i);
    }

    public function inspection(Inspection $inspection)
    {
        $this->can('inspections.view');
        $inspection->load('jobCard.vehicle.make', 'jobCard.vehicle.vehicleModel', 'jobCard.customer', 'results', 'findings.service', 'findings.part', 'technician', 'template');

        return view('workshop::inspection', ['inspection' => $inspection, 'suggestions' => app(\App\Modules\Reports\Services\AiAssistant::class)->suggestForJob($inspection->jobCard)]);
    }

    public function saveInspection(Request $request, Inspection $inspection)
    {
        $this->can('inspections.update');
        $data = $request->validate(['results' => ['nullable', 'array'], 'results.*.result' => ['in:ok,attention,urgent,na'], 'results.*.notes' => ['nullable', 'string', 'max:250'],
            'summary' => ['nullable', 'string', 'max:5000'], 'recommendations' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array'], 'files.*' => ['file', 'max:'.config('erp.uploads.max_kb'), 'mimes:'.config('erp.uploads.mimes')]]);
        $this->inspections->saveResults($inspection, $data['results'] ?? [], $data['summary'] ?? null, $data['recommendations'] ?? null);
        foreach ($request->file('files', []) as $f) {
            FileStorage::attach($inspection, $f, 'inspection');
        }

        return back()->with('success', __('Inspection saved.'));
    }

    public function addFinding(Request $request, Inspection $inspection)
    {
        $this->can('inspections.update');
        $data = $request->validate([
            'description' => ['required', 'string', 'max:250'], 'dtc_code' => ['nullable', 'string', 'max:20', 'regex:/^[PBCU][0-9A-F]{4}$/i'],
            'severity' => ['required', 'in:low,medium,high,critical'], 'service_id' => ['nullable', 'integer'], 'part_id' => ['nullable', 'integer'],
            'qty' => ['nullable', 'numeric', 'gt:0'], 'estimated_price' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:250'],
        ]);
        $this->inspections->addFinding($inspection, $data);

        return back()->with('success', __('Finding added.'));
    }

    public function deleteFinding(InspectionFinding $finding)
    {
        $this->can('inspections.update');
        $inspection = Inspection::query()->findOrFail($finding->inspection_id);
        abort_if($inspection->status === 'completed', 403);
        $finding->delete();

        return back();
    }

    public function completeInspection(Inspection $inspection)
    {
        $this->can('inspections.update');
        $this->inspections->complete($inspection);

        return back()->with('success', __('Inspection completed.'));
    }

    public function quotationFromInspection(Inspection $inspection)
    {
        $this->can('quotations.create');
        $q = $this->quotes->fromInspection($inspection);

        return redirect()->route('quotations.edit', $q)->with('success', __('Quotation drafted from inspection findings. Review prices and send.'));
    }

    public function printInspection(Inspection $inspection)
    {
        $this->can('inspections.view');
        $inspection->load('jobCard.vehicle.make', 'jobCard.vehicle.vehicleModel', 'jobCard.customer', 'results', 'findings', 'technician', 'attachments');
        $html = view('workshop::print.inspection', ['inspection' => $inspection])->render();

        return request('format') === 'pdf'
            ? response(Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf'])
            : response($html);
    }

    // ---------------------------------------------------------- quotations

    public function createQuotation(Request $request)
    {
        $this->can('quotations.create');
        $jc = $request->query('job_card_id') ? JobCard::query()->with('customer', 'vehicle')->find($request->query('job_card_id')) : null;

        return view('workshop::quotation-form', ['quotation' => null, 'jc' => $jc, 'rows' => []]);
    }

    public function storeQuotation(QuotationRequest $request)
    {
        $data = $request->validated();
        $jc = ! empty($data['job_card_id']) ? JobCard::query()->findOrFail($data['job_card_id']) : null;
        $q = $this->quotes->create([
            'type' => $jc ? 'workshop' : 'sales', 'job_card_id' => $jc?->id, 'customer_id' => $jc?->customer_id ?? $data['customer_id'],
            'vehicle_id' => $jc?->vehicle_id ?? ($data['vehicle_id'] ?? null), 'branch_id' => $jc?->branch_id,
            'valid_until' => $data['valid_until'] ?? null, 'notes' => $data['notes'] ?? null, 'terms' => $data['terms'] ?? null,
        ], $data['items']);
        if ($jc && $jc->status === JobCard::PENDING_INSPECTION) {
            $this->jobs->changeStatus($jc, JobCard::AWAITING_QUOTATION, null, true);
        }

        return redirect()->route('quotations.show', $q)->with('success', __('Quotation created.'));
    }

    public function quotation(Quotation $quotation)
    {
        $this->can('quotations.view');
        $quotation->load('items.part', 'items.service', 'customer', 'vehicle.make', 'vehicle.vehicleModel', 'jobCard', 'creator', 'revisions', 'parent');
        $versions = Quotation::query()->where(fn ($q) => $q->where('id', $quotation->parent_id ?: $quotation->id)->orWhere('parent_id', $quotation->parent_id ?: $quotation->id))->orderBy('version')->get();

        return view('workshop::quotation', ['q' => $quotation, 'versions' => $versions]);
    }

    public function editQuotation(Quotation $quotation)
    {
        $this->can('quotations.update');
        abort_unless($quotation->isEditable(), 403, __('Only draft or sent quotations can be edited.'));
        $rows = $quotation->items->map(fn ($i) => [
            'id' => $i->id, 'item_type' => $i->item_type, 'part_id' => $i->part_id, 'service_id' => $i->service_id, 'description' => $i->description,
            'qty' => (float) $i->qty, 'unit_price' => (float) $i->unit_price, 'discount' => (float) $i->discount, 'tax_rate' => (float) $i->tax_rate,
            'option_group' => $i->option_group, 'label' => $i->part?->label() ?? $i->service?->displayName() ?? '',
        ])->all();

        return view('workshop::quotation-form', ['quotation' => $quotation, 'jc' => $quotation->jobCard, 'rows' => $rows]);
    }

    public function updateQuotation(QuotationRequest $request, Quotation $quotation)
    {
        $data = $request->validated();
        $this->quotes->update($quotation, $data, $data['items']);

        return redirect()->route('quotations.show', $quotation)->with('success', __('Quotation updated.'));
    }

    public function sendQuotation(Request $request, Quotation $quotation)
    {
        $this->can('quotations.update');
        $this->quotes->send($quotation, (array) $request->input('channels', ['sms', 'whatsapp', 'mail']));

        return back()->with('success', __('Quotation sent to the customer.'));
    }

    public function respondQuotation(Request $request, Quotation $quotation)
    {
        $this->can('quotations.approve');
        $data = $request->validate([
            'decisions' => ['nullable', 'array'], 'decisions.*' => ['in:approved,rejected'],
            'response_by' => ['required', 'string', 'max:190'], 'channel' => ['required', 'in:in_person,phone,whatsapp,email'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->quotes->respond($quotation, $data['decisions'] ?? [], $data['response_by'], $data['channel'], $data['notes'] ?? null);

        return back()->with('success', __('Customer response recorded.'));
    }

    public function reviseQuotation(Quotation $quotation)
    {
        $this->can('quotations.create');
        $new = $this->quotes->revise($quotation);

        return redirect()->route('quotations.edit', $new)->with('success', __('New version :v created.', ['v' => $new->version]));
    }

    /** Sales quotation -> invoice draft or sales order. */
    public function convertQuotation(Request $request, Quotation $quotation, InvoiceService $invoices)
    {
        $this->can('invoices.create');
        abort_unless(in_array($quotation->status, ['approved', 'partially_approved', 'sent', 'draft'], true) && ! $quotation->job_card_id, 403);
        $items = $quotation->items()->where('approval_status', '!=', 'rejected')->get()->map(fn ($i) => [
            'item_type' => $i->item_type, 'part_id' => $i->part_id, 'service_id' => $i->service_id, 'description' => $i->description,
            'qty' => $i->qty, 'unit_price' => $i->unit_price, 'discount' => $i->discount, 'tax_rate' => $i->tax_rate,
        ])->all();
        $inv = $invoices->createDraft(['customer_id' => $quotation->customer_id, 'type' => 'sale', 'notes' => __('From quotation').' '.$quotation->number], $items);
        $quotation->update(['status' => 'converted']);

        return redirect()->route('invoices.show', $inv)->with('success', __('Invoice draft created from quotation.'));
    }

    public function printQuotation(Quotation $quotation)
    {
        $this->can('quotations.print');
        $quotation->load('items', 'customer', 'vehicle.make', 'vehicle.vehicleModel', 'creator');
        $html = view('workshop::print.quotation', ['q' => $quotation])->render();

        return request('format') === 'pdf'
            ? response(Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$quotation->number.'.pdf"'])
            : response($html);
    }

    // ------------------------------------------------------------ technicians

    public function technicians(Department $department)
    {
        $this->can('departments.update');

        return view('workshop::technicians', [
            'department' => $department->load('technicians'),
            'users' => User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function saveTechnicians(Request $request, Department $department)
    {
        $this->can('departments.update');
        $ids = User::query()->where('company_id', tenant()->id())->whereIn('id', (array) $request->input('users', []))->pluck('id');
        $department->technicians()->sync($ids);

        return back()->with('success', __('Technicians saved.'));
    }
}
