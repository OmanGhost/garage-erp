<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Core\Services\IntegrationManager;
use App\Modules\CRM\Models\Customer;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Vehicles\Models\VehicleMake;
use App\Modules\Vehicles\Models\VehicleOwnership;
use App\Support\Audit\AuditLogger;
use App\Support\Export\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function show(Customer $customer)
    {
        abort_unless(auth()->user()->can('customers.view'), 403);
        $customer->load('group', 'contacts', 'vehicles.make', 'vehicles.vehicleModel', 'portalUser');

        return view('crm::customer', [
            'c' => $customer,
            'jobs' => $customer->jobCards()->with('vehicle')->latest('id')->limit(20)->get(),
            'invoices' => $customer->invoices()->whereNotIn('status', ['draft', 'held'])->latest('id')->limit(20)->get(),
            'quotations' => $customer->quotations()->latest('id')->limit(10)->get(),
            'complaints' => $customer->complaints()->latest('id')->limit(10)->get(),
            'balance' => $customer->balance(),
            'credit' => app(\App\Modules\Sales\Services\CustomerCreditService::class)->available($customer),
            'stats' => [
                'jobs' => $customer->jobCards()->count(),
                'spent' => (string) $customer->invoices()->whereIn('status', ['posted', 'partially_paid', 'paid'])->sum('total'),
                'last_visit' => $customer->jobCards()->max('received_at'),
            ],
        ]);
    }

    public function statement(Request $request, Customer $customer)
    {
        abort_unless(auth()->user()->can('customers.view'), 403);
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $st = Ledger::partyStatement('customer', $customer->id, $from, $to);
        $html = view('crm::statement', ['party' => $customer, 'st' => $st, 'from' => $from, 'to' => $to, 'type' => 'customer'])->render();

        return $request->query('format') === 'pdf'
            ? response(Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="statement-'.$customer->code.'.pdf"'])
            : response($html);
    }

    /** Create / reset the customer portal account. */
    public function portalAccess(Request $request, Customer $customer)
    {
        abort_unless(auth()->user()->can('customers.update'), 403);
        $data = $request->validate(['email' => ['required', 'email', Rule::unique('users', 'email')->ignore($customer->portalUser?->id)]]);
        $password = Str::password(12, symbols: false);
        $user = $customer->portalUser ?? new User;
        $user->fill(['name' => $customer->name, 'email' => strtolower($data['email']), 'phone' => $customer->phone, 'password' => $password, 'locale' => app()->getLocale()]);
        $user->company_id = tenant()->id();
        $user->customer_id = $customer->id;
        $user->is_active = true;
        $user->save();
        setPermissionsTeamId(tenant()->id());
        $user->syncRoles(['Customer']);
        if (! $customer->email) {
            $customer->update(['email' => $user->email]);
        }
        AuditLogger::log('customer.portal_access', $customer, ['email' => $user->email]);

        return back()->with('success', __('Portal access created. Temporary password: :p', ['p' => $password]));
    }

    public function vehicle(Vehicle $vehicle)
    {
        abort_unless(auth()->user()->can('vehicles.view'), 403);
        $vehicle->load('customer', 'make', 'vehicleModel', 'fleet', 'insuranceCompany', 'ownerships.fromCustomer', 'ownerships.toCustomer', 'accidents', 'warranties');
        $jobs = $vehicle->jobCards()->with('tasks', 'parts.part', 'invoices')->latest('id')->get();
        $replaced = DB::table('job_card_parts as p')->join('job_cards as j', 'j.id', '=', 'p.job_card_id')->join('parts as pt', 'pt.id', '=', 'p.part_id')
            ->where('j.vehicle_id', $vehicle->id)->where('j.company_id', tenant()->id())->whereColumn('p.qty_issued', '>', 'p.qty_returned')
            ->select('pt.sku', 'pt.name_ar', 'pt.name_en', 'p.qty_issued', 'p.qty_returned', 'p.part_condition', 'j.number', 'j.odometer_in', 'p.issued_at')
            ->orderByDesc('p.issued_at')->get();

        return view('crm::vehicle', ['v' => $vehicle, 'jobs' => $jobs, 'replaced' => $replaced, 'compatible' => $this->compatibleParts($vehicle)]);
    }

    /**
     * Active parts whose compatibility list matches this vehicle: same make, model (or any model of the make),
     * year inside the range, engine and VIN pattern when given. With stock on hand and the fitment note.
     */
    protected function compatibleParts(Vehicle $vehicle): \Illuminate\Support\Collection
    {
        if (! $vehicle->vehicle_make_id || ! auth()->user()->can('parts.view')) {
            return collect();
        }
        $year = $vehicle->year ? (int) $vehicle->year : null;
        $fits = \App\Modules\Inventory\Models\PartCompatibility::query()
            ->where('vehicle_make_id', $vehicle->vehicle_make_id)
            ->where(fn ($q) => $q->whereNull('vehicle_model_id')->orWhere('vehicle_model_id', $vehicle->vehicle_model_id))
            ->when($year, fn ($q) => $q->where(fn ($w) => $w->whereNull('year_from')->orWhere('year_from', '<=', $year))
                ->where(fn ($w) => $w->whereNull('year_to')->orWhere('year_to', '>=', $year)))
            ->get()
            ->filter(function ($c) use ($vehicle) {
                if ($c->engine && $vehicle->engine_type && stripos((string) $vehicle->engine_type, (string) $c->engine) === false
                    && stripos((string) $vehicle->engine_capacity, (string) $c->engine) === false) {
                    return false;
                }
                if ($c->vin_pattern && $vehicle->vin) {
                    $re = '/^'.str_replace(['\*', '\?'], ['.*', '.'], preg_quote(strtoupper($c->vin_pattern), '/')).'/';

                    return (bool) preg_match($re, strtoupper($vehicle->vin));
                }

                return true;
            });
        if ($fits->isEmpty()) {
            return collect();
        }
        $parts = \App\Modules\Inventory\Models\Part::query()->with('category', 'brand')
            ->whereIn('id', $fits->pluck('part_id')->unique())->where('is_active', true)->orderBy('name_ar')->get();
        $stock = DB::table('stock_levels')->where('company_id', tenant()->id())->whereIn('part_id', $parts->pluck('id'))
            ->selectRaw('part_id, sum(qty_on_hand - qty_reserved) as available')->groupBy('part_id')->pluck('available', 'part_id');

        return $parts->map(function ($p) use ($fits, $stock) {
            $f = $fits->firstWhere('part_id', $p->id);
            $years = $f->year_from || $f->year_to ? trim(($f->year_from ?: '…').'–'.($f->year_to ?: '…')) : null;

            return (object) [
                'part' => $p, 'available' => (float) ($stock[$p->id] ?? 0),
                'fit' => trim(implode(' · ', array_filter([$f->vehicle_model_id ? null : __('All models'), $years, $f->engine, $f->notes]))),
            ];
        })->sortByDesc('available')->values();
    }

    public function transferVehicle(Request $request, Vehicle $vehicle)
    {
        abort_unless(auth()->user()->can('vehicles.update'), 403);
        $data = $request->validate(['to_customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', tenant()->id())], 'notes' => ['nullable', 'string', 'max:250']]);
        DB::transaction(function () use ($vehicle, $data) {
            VehicleOwnership::create(['vehicle_id' => $vehicle->id, 'from_customer_id' => $vehicle->customer_id, 'to_customer_id' => $data['to_customer_id'],
                'changed_at' => now()->toDateString(), 'notes' => $data['notes'] ?? null, 'user_id' => auth()->id()]);
            $vehicle->update(['customer_id' => $data['to_customer_id']]);
        });

        return back()->with('success', __('Ownership transferred.'));
    }

    public function decodeVin(Request $request)
    {
        $vin = strtoupper((string) $request->query('vin'));
        if (! IntegrationManager::active('vin')) {
            return response()->json(['ok' => false, 'message' => __('VIN decoder integration is not enabled (Integrations → VIN Decoder).')]);
        }
        $data = IntegrationManager::decodeVin($vin);
        if (! $data) {
            return response()->json(['ok' => false, 'message' => __('VIN could not be decoded.')]);
        }
        $make = $data['make'] ? VehicleMake::query()->whereRaw('UPPER(name) = ?', [strtoupper($data['make'])])->first() : null;

        return response()->json(['ok' => true, 'data' => $data, 'make_id' => $make?->id]);
    }
}
