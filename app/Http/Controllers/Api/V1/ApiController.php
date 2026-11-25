<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\Core\Models\Integration;
use App\Modules\CRM\Models\Customer;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Services\PayrollService;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Services\PartSearch;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Services\JobCardService;
use App\Support\DocumentNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * JSON REST API. Every endpoint checks the same permissions as the web UI;
 * customer tokens only see their own records.
 */
class ApiController extends Controller
{
    protected function can(string $perm): void
    {
        abort_unless(request()->user()->can($perm), 403, 'Forbidden');
    }

    protected function customerScope($q)
    {
        $u = request()->user();

        return $u->isCustomer() ? $q->where('customer_id', $u->customer_id) : $q;
    }

    protected function perPage(Request $r): int
    {
        return min(100, max(1, (int) $r->query('per_page', 25)));
    }

    public function me(Request $request)
    {
        $u = $request->user();

        return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'company' => tenant()->company()?->only(['id', 'name', 'currency_code', 'vat_rate']),
            'customer_id' => $u->customer_id, 'roles' => $u->getRoleNames(), 'branch_id' => $u->branch_id];
    }

    // -------------------------------------------------------------- customers

    public function customers(Request $r)
    {
        $this->can('customers.view');

        return Customer::query()->when($r->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")))
            ->select(['id', 'code', 'type', 'name', 'name_en', 'phone', 'email', 'classification', 'credit_limit', 'is_active', 'created_at'])->paginate($this->perPage($r));
    }

    public function customer(Customer $customer)
    {
        abort_if(request()->user()->isCustomer() && request()->user()->customer_id !== $customer->id, 404);
        request()->user()->isCustomer() || $this->can('customers.view');

        return $customer->load('vehicles')->toArray() + ['balance' => $customer->balance()];
    }

    public function storeCustomer(Request $r)
    {
        $this->can('customers.create');
        $data = $r->validate(['name' => ['required', 'string', 'max:190'], 'phone' => ['required', 'string', 'max:30'], 'email' => ['nullable', 'email'],
            'type' => ['nullable', 'in:individual,company,government'], 'vat_number' => ['nullable', 'string', 'max:50']]);
        $c = Customer::create(array_merge($data, ['code' => DocumentNumber::next('customer', false), 'phone' => omani_phone($data['phone'])]));

        return response()->json($c, 201);
    }

    // --------------------------------------------------------------- vehicles

    public function vehicles(Request $r)
    {
        $r->user()->isCustomer() || $this->can('vehicles.view');

        return $this->customerScope(Vehicle::query())->with('make:id,name', 'vehicleModel:id,name')
            ->when($r->query('plate'), fn ($q, $p) => $q->where('plate_number', $p))->when($r->query('vin'), fn ($q, $v) => $q->where('vin', strtoupper($v)))
            ->paginate($this->perPage($r));
    }

    public function vehicle(Vehicle $vehicle)
    {
        $u = request()->user();
        abort_if($u->isCustomer() && $vehicle->customer_id !== $u->customer_id, 404);
        $u->isCustomer() || $this->can('vehicles.view');

        return $vehicle->load('make', 'vehicleModel', 'jobCards:id,vehicle_id,number,status,received_at,delivered_at');
    }

    public function storeVehicle(Request $r)
    {
        $this->can('vehicles.create');
        $c = tenant()->id();
        $data = $r->validate(['customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $c)], 'plate_number' => ['required', 'string', 'max:20'],
            'plate_code' => ['nullable', 'string', 'max:10'], 'vin' => ['nullable', 'string', 'size:17'], 'vehicle_make_id' => ['nullable', 'exists:vehicle_makes,id'],
            'vehicle_model_id' => ['nullable', 'exists:vehicle_models,id'], 'year' => ['nullable', 'integer'], 'odometer' => ['nullable', 'integer', 'min:0']]);

        return response()->json(Vehicle::create($data), 201);
    }

    // ------------------------------------------------------------------ parts

    public function parts(Request $r)
    {
        $this->can('parts.view');

        return PartSearch::query($r->only(['q', 'make_id', 'model_id', 'year', 'vin', 'brand_id', 'part_category_id', 'in_stock']))
            ->where('is_active', true)->paginate($this->perPage($r));
    }

    public function part(Part $part)
    {
        $this->can('parts.view');

        return $part->load('brand', 'category', 'alternatives', 'compatibilities', 'stockLevels.warehouse:id,name');
    }

    public function stock(Request $r)
    {
        $this->can('stock.view');

        return StockLevel::query()->with('part:id,sku,name_ar,name_en', 'warehouse:id,code,name')
            ->when($r->query('warehouse_id'), fn ($q, $w) => $q->where('warehouse_id', $w))->when($r->query('part_id'), fn ($q, $p) => $q->where('part_id', $p))
            ->paginate($this->perPage($r));
    }

    // ------------------------------------------------------------- job cards

    public function jobCards(Request $r)
    {
        $r->user()->isCustomer() || $this->can('job_cards.view');

        return $this->customerScope(JobCard::query())->with('vehicle:id,plate_number,plate_code,vin', 'customer:id,name,phone')
            ->when($r->query('status'), fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate($this->perPage($r));
    }

    public function jobCard(JobCard $jobCard)
    {
        $u = request()->user();
        abort_if($u->isCustomer() && $jobCard->customer_id !== $u->customer_id, 404);
        $u->isCustomer() || $this->can('job_cards.view');

        return $jobCard->load('vehicle', 'customer:id,name,phone', 'tasks', 'parts.part:id,sku,name_ar', 'histories');
    }

    public function storeJobCard(Request $r, JobCardService $jobs)
    {
        $this->can('reception.create');
        $c = tenant()->id();
        $data = $r->validate(['customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $c)],
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('company_id', $c)], 'customer_complaint' => ['required', 'string'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'], 'odometer_in' => ['nullable', 'integer'], 'promised_at' => ['nullable', 'date']]);

        return response()->json($jobs->create($data), 201);
    }

    public function jobCardStatus(Request $r, JobCard $jobCard, JobCardService $jobs)
    {
        $this->can('job_cards.change_status');
        $data = $r->validate(['status' => ['required', 'in:'.implode(',', JobCard::STATUSES)], 'notes' => ['nullable', 'string', 'max:250']]);

        return $jobs->changeStatus($jobCard, $data['status'], $data['notes'] ?? null);
    }

    // --------------------------------------------------------------- invoices

    public function invoices(Request $r)
    {
        $r->user()->isCustomer() || $this->can('invoices.view');

        return $this->customerScope(Invoice::query())->whereNotNull('number')->with('customer:id,name')
            ->when($r->query('from'), fn ($q, $d) => $q->whereDate('issue_date', '>=', $d))->when($r->query('to'), fn ($q, $d) => $q->whereDate('issue_date', '<=', $d))
            ->latest('id')->paginate($this->perPage($r));
    }

    public function invoice(Invoice $invoice)
    {
        $u = request()->user();
        abort_if($u->isCustomer() && $invoice->customer_id !== $u->customer_id, 404);
        $u->isCustomer() || $this->can('invoices.view');

        return $invoice->load('items', 'customer:id,name,vat_number', 'allocations')->toArray() + ['balance_due' => $invoice->balanceDue()];
    }

    // ----------------------------------------------------------- appointments

    public function appointments(Request $r)
    {
        $r->user()->isCustomer() || $this->can('appointments.view');

        return $this->customerScope(Appointment::query())->with('vehicle:id,plate_number')->where('starts_at', '>=', $r->query('from', now()->startOfDay()))
            ->orderBy('starts_at')->paginate($this->perPage($r));
    }

    public function storeAppointment(Request $r, AppointmentService $svc)
    {
        $u = $r->user();
        $u->isCustomer() || $this->can('appointments.create');
        $c = tenant()->id();
        $data = $r->validate(['customer_id' => [$u->isCustomer() ? 'nullable' : 'required', Rule::exists('customers', 'id')->where('company_id', $c)],
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->where('company_id', $c)], 'starts_at' => ['required', 'date', 'after:now'],
            'duration' => ['nullable', 'integer', 'min:15'], 'service_id' => ['nullable', 'integer'], 'notes' => ['nullable', 'string', 'max:500']]);
        if ($u->isCustomer()) {
            $data['customer_id'] = $u->customer_id;
            $data['source'] = 'portal';
        }

        return response()->json($svc->book($data + ['duration' => 60]), 201);
    }

    public function journal(Request $r)
    {
        $this->can('journal_entries.export');

        return \App\Modules\Accounting\Models\JournalEntry::query()->with('lines.account:id,code,name_ar,name_en')
            ->when($r->query('from'), fn ($q, $d) => $q->whereDate('date', '>=', $d))->when($r->query('to'), fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->orderBy('id')->paginate($this->perPage($r));
    }

    /**
     * Attendance devices push punches: header X-Device-Token = token configured in
     * Integrations -> Attendance. Body: employee_code, timestamp, type (in|out).
     */
    public function attendancePunch(Request $r, PayrollService $payroll)
    {
        $token = (string) $r->header('X-Device-Token');
        abort_if(strlen($token) < 16, 401);
        $integration = Integration::query()->withoutGlobalScopes()->where('provider', 'attendance')->where('is_active', true)->get()
            ->first(fn ($i) => hash_equals((string) ($i->credentials['token'] ?? ''), $token));
        abort_unless($integration, 401);
        tenant()->set($integration->company_id);
        $data = $r->validate(['employee_code' => ['required', 'string'], 'timestamp' => ['required', 'date'], 'type' => ['required', 'in:in,out']]);
        $e = Employee::query()->where('code', $data['employee_code'])->firstOrFail();
        $at = Carbon::parse($data['timestamp']);
        $a = $data['type'] === 'in' ? $payroll->checkIn($e, $at, 'device') : $payroll->checkOut($e, $at, 'device');

        return response()->json(['ok' => true, 'attendance_id' => $a->id]);
    }
}
