<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\CRM\Models\Customer;
use App\Modules\HR\Models\Employee;
use App\Modules\Inventory\Services\PartSearch;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Vehicles\Models\VehicleModel;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\Service;
use Illuminate\Http\Request;

/**
 * JSON endpoints for searchable selects (always tenant-scoped).
 */
class LookupController extends Controller
{
    public function __invoke(Request $request, string $type)
    {
        $q = trim((string) $request->query('q'));
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $limit = 20;

        $results = match ($type) {
            'customers' => Customer::query()->where('is_active', true)
                ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('name_en', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('code', $q)))
                ->limit($limit)->get()->map(fn ($c) => ['id' => $c->id, 'text' => $c->name.($c->phone ? ' — '.$c->phone : ''),
                    'extra' => ['price_level' => $c->price_level, 'discount' => (string) $c->discount_percent]]),
            'vehicles' => Vehicle::query()->with('make', 'vehicleModel', 'customer')
                ->when($request->query('customer_id'), fn ($x, $c) => $x->where('customer_id', $c))
                ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('plate_number', 'like', $like)->orWhere('vin', 'like', $like)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('phone', 'like', $like))))
                ->limit($limit)->get()->map(fn ($v) => ['id' => $v->id, 'text' => $v->label().' ('.$v->customer?->name.')',
                    'extra' => ['customer_id' => $v->customer_id, 'customer' => $v->customer?->name, 'odometer' => $v->odometer, 'vehicle_type' => $v->vehicle_type]]),
            'parts' => PartSearch::query(['q' => $q, 'warehouse_id' => null])->where('is_active', true)->limit($limit)->get()
                ->map(fn ($p) => ['id' => $p->id, 'text' => $p->label().' ['.qty($p->on_hand ?? 0).']',
                    'extra' => ['sale_price' => (string) $p->sale_price, 'wholesale_price' => (string) $p->wholesale_price, 'workshop_price' => (string) $p->workshop_price,
                        'purchase_price' => (string) $p->purchase_price, 'tax_rate' => (string) $p->tax_rate, 'on_hand' => (string) ($p->on_hand ?? 0), 'min_sale_price' => (string) $p->min_sale_price]]),
            'services' => Service::query()->where('is_active', true)->with('vehiclePrices')
                ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('name_ar', 'like', $like)->orWhere('name_en', 'like', $like)->orWhere('code', $q)))
                ->limit($limit)->get()->map(fn ($s) => ['id' => $s->id, 'text' => $s->code.' — '.$s->displayName(),
                    'extra' => ['price' => $s->priceFor($request->query('vehicle_type')), 'tax_rate' => (string) $s->tax_rate, 'minutes' => $s->estimated_minutes, 'department_id' => $s->department_id]]),
            'suppliers' => Supplier::query()->where('is_active', true)
                ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', $q)->orWhere('phone', 'like', $like)))
                ->limit($limit)->get()->map(fn ($s) => ['id' => $s->id, 'text' => $s->code.' — '.$s->name]),
            'users', 'technicians' => User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->where('is_active', true)
                ->when($type === 'technicians', fn ($x) => $x->whereHas('employee', fn ($e) => $e->where('is_technician', true)))
                ->when($q, fn ($x) => $x->where('name', 'like', $like))->limit($limit)->get()->map(fn ($u) => ['id' => $u->id, 'text' => $u->name]),
            'employees' => Employee::query()->when($q, fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', $q)))
                ->limit($limit)->get()->map(fn ($e) => ['id' => $e->id, 'text' => $e->code.' — '.$e->name]),
            'accounts' => Account::query()->where('is_group', false)->where('is_active', true)
                ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('code', 'like', $q.'%')->orWhere('name_ar', 'like', $like)->orWhere('name_en', 'like', $like)))
                ->orderBy('code')->limit($limit)->get()->map(fn ($a) => ['id' => $a->id, 'text' => $a->label()]),
            'job_cards' => JobCard::query()->with('vehicle', 'customer')
                ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('number', 'like', $like)->orWhereHas('vehicle', fn ($v) => $v->where('plate_number', 'like', $like))))
                ->latest('id')->limit($limit)->get()->map(fn ($j) => ['id' => $j->id, 'text' => $j->number.' — '.$j->vehicle?->plate().' — '.$j->customer?->name]),
            'vehicle_models' => VehicleModel::query()->when($request->query('make_id'), fn ($x, $m) => $x->where('vehicle_make_id', $m))
                ->when($q, fn ($x) => $x->where('name', 'like', $like))->orderBy('name')->limit(50)->get()->map(fn ($m) => ['id' => $m->id, 'text' => $m->name]),
            default => abort(404),
        };

        return response()->json(['results' => $results->values()]);
    }
}
