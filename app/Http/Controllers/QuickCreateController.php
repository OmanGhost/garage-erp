<?php

namespace App\Http\Controllers;

use App\Support\Crud\CrudRegistry;
use App\Support\Crud\CrudResource;
use App\Support\Crud\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Add new" from inside a search field (job card, invoice, purchase order…): a short form in a dialog that
 * creates the customer / vehicle / part / service / supplier with the same rules as its full form, then
 * selects it in the field that opened the dialog.
 */
class QuickCreateController extends CrudController
{
    /** lookup type => [crud slug, fields shown in the dialog, field that receives the typed search text] */
    public const TYPES = [
        'customers' => ['customers', ['type', 'name', 'phone', 'email', 'vat_number', 'city'], 'name'],
        'vehicles' => ['vehicles', ['customer_id', 'plate_number', 'plate_code', 'vehicle_make_id', 'vehicle_model_id', 'year', 'vin', 'color', 'odometer'], 'plate_number'],
        'parts' => ['parts', ['name_ar', 'name_en', 'oem_number', 'barcode', 'part_category_id', 'brand_id', 'part_type', 'unit', 'purchase_price', 'sale_price', 'tax_rate'], 'name_ar'],
        'services' => ['services', ['code', 'name_ar', 'name_en', 'department_id', 'price', 'estimated_minutes', 'tax_rate'], 'name_ar'],
        'suppliers' => ['suppliers', ['name', 'phone', 'email', 'vat_number', 'city'], 'name'],
    ];

    protected function quickResource(string $type): CrudResource
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return $this->resource(self::TYPES[$type][0], 'create');
    }

    /** @return array<int, Field> the dialog fields: the short list plus anything the full form requires */
    protected function quickFields(CrudResource $r, string $type): array
    {
        $names = self::TYPES[$type][1];

        return array_values(array_filter($r->formFields(null), fn (Field $f) => in_array($f->name, $names, true) || $f->required));
    }

    public function form(Request $request, string $type)
    {
        $r = $this->quickResource($type);
        $fields = $this->quickFields($r, $type);
        // prefill: the text typed in the search box, and context such as the job card's customer
        $model = new ($r::$model);
        $q = trim((string) $request->query('q'));
        if ($q !== '') {
            $model->setAttribute(self::TYPES[$type][2], $type === 'vehicles' ? strtoupper($q) : $q);
        }
        if ($type === 'vehicles' && ($cid = (int) $request->query('customer_id'))) {
            $customer = \App\Modules\CRM\Models\Customer::query()->find($cid);
            if ($customer) {
                $model->setAttribute('customer_id', $customer->id);
                $model->setRelation('customer', $customer);
            }
        }
        foreach ($fields as $f) {
            if ($model->getAttribute($f->name) === null && $f->default !== null) {
                $model->setAttribute($f->name, $f->default);
            }
        }

        return view('crud._quick', ['resource' => $r, 'fields' => $fields, 'model' => $model, 'type' => $type]);
    }

    public function store(Request $request, string $type)
    {
        $r = $this->quickResource($type);
        $fields = $this->quickFields($r, $type);
        $names = array_map(fn (Field $f) => $f->name, $fields);
        $rules = array_intersect_key($r->rules(null), array_flip($names));
        $request->validate($rules, [], collect($fields)->mapWithKeys(fn (Field $f) => [$f->name => $f->getLabel()])->all());

        // fields not in the dialog keep their defaults (switches would otherwise read as "off")
        foreach ($r->formFields(null) as $f) {
            if (! in_array($f->name, $names, true)) {
                $f->type === 'checkbox' ? $request->merge([$f->name => (bool) $f->default]) : $request->request->remove($f->name);
                if ($f->type !== 'checkbox' && $f->default !== null) {
                    $request->merge([$f->name => $f->default]);
                }
            }
        }
        $model = DB::transaction(function () use ($r, $request) {
            $data = $this->payload($r, $request, null);
            $model = new ($r::$model);
            $model->fill($r->fillable($data));
            $r->saving($model, $data, true);
            $model->save();
            $r->saved($model, $data, true);

            return $model;
        });
        \App\Support\Audit\AuditLogger::log('quick_create.'.$type, $model);

        return response()->json(['item' => LookupController::item($type, $this->reload($model, $type), $request),
            'message' => __('«:name» was added to :list.', ['name' => $r->recordLabel($model), 'list' => $r->title()])]);
    }

    protected function reload(Model $m, string $type): Model
    {
        return match ($type) {
            'vehicles' => $m->load('make', 'vehicleModel', 'customer'),
            default => $m,
        };
    }
}
