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
                ->limit($limit)->get()->map(fn ($c) => self::item('customers', $c, $request)),
            'vehicles' => Vehicle::query()->with('make', 'vehicleModel', 'customer')
                ->when($request->query('customer_id'), fn ($x, $c) => $x->where('customer_id', $c))
                ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('plate_number', 'like', $like)->orWhere('vin', 'like', $like)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('phone', 'like', $like))))
                ->limit($limit)->get()->map(fn ($v) => self::item('vehicles', $v, $request)),
            'parts' => PartSearch::query(['q' => $q, 'warehouse_id' => null])->where('is_active', true)->limit($limit)->get()
                ->map(fn ($p) => self::item('parts', $p, $request)),
            'services' => Service::query()->where('is_active', true)->with('vehiclePrices')
                ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('name_ar', 'like', $like)->orWhere('name_en', 'like', $like)->orWhere('code', $q)))
                ->limit($limit)->get()->map(fn ($s) => self::item('services', $s, $request)),
            'suppliers' => Supplier::query()->where('is_active', true)
                ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', $q)->orWhere('phone', 'like', $like)))
                ->limit($limit)->get()->map(fn ($s) => self::item('suppliers', $s, $request)),
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

    /** One result row (also returned by the quick-add dialog for the record it just created). */
    public static function item(string $type, \Illuminate\Database\Eloquent\Model $m, Request $request): array
    {
        return match ($type) {
            'customers' => ['id' => $m->id, 'text' => $m->name.($m->phone ? ' — '.$m->phone : ''),
                'extra' => ['price_level' => $m->price_level, 'discount' => (string) $m->discount_percent]],
            'vehicles' => ['id' => $m->id, 'text' => $m->label().' ('.$m->customer?->name.')',
                'extra' => ['customer_id' => $m->customer_id, 'customer' => $m->customer?->name, 'odometer' => $m->odometer, 'vehicle_type' => $m->vehicle_type]],
            'parts' => ['id' => $m->id, 'text' => $m->label().' ['.qty($m->on_hand ?? 0).']',
                'extra' => ['sale_price' => (string) $m->sale_price, 'wholesale_price' => (string) $m->wholesale_price, 'workshop_price' => (string) $m->workshop_price,
                    'purchase_price' => (string) $m->purchase_price, 'tax_rate' => (string) $m->tax_rate, 'on_hand' => (string) ($m->on_hand ?? 0), 'min_sale_price' => (string) $m->min_sale_price]],
            'services' => ['id' => $m->id, 'text' => $m->code.' — '.$m->displayName(),
                'extra' => ['price' => $m->priceFor($request->query('vehicle_type')), 'tax_rate' => (string) $m->tax_rate, 'minutes' => $m->estimated_minutes, 'department_id' => $m->department_id]],
            'suppliers' => ['id' => $m->id, 'text' => $m->code.' — '.$m->name],
            default => ['id' => $m->getKey(), 'text' => (string) $m->getKey()],
        };
    }
}
