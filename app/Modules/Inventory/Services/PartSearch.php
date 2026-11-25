<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Part;
use App\Modules\Vehicles\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * Advanced parts search: name (ar/en), SKU, barcode, OEM & alternative numbers,
 * serial numbers, brand, category, vehicle make/model/year and VIN compatibility.
 */
class PartSearch
{
    public static function query(array $f): Builder
    {
        $q = Part::query()->with(['brand', 'category'])->withSum('stockLevels as on_hand', 'qty_on_hand')
            ->withSum('stockLevels as reserved', 'qty_reserved');

        if ($term = trim((string) ($f['q'] ?? ''))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $q->where(function ($w) use ($term, $like) {
                $w->where('sku', $term)->orWhere('barcode', $term)->orWhere('oem_number', $term)
                    ->orWhere('name_ar', 'like', $like)->orWhere('name_en', 'like', $like)
                    ->orWhere('sku', 'like', $like)->orWhere('oem_number', 'like', $like)->orWhere('serial_number', $term)
                    ->orWhereHas('alternatives', fn ($a) => $a->where('number', 'like', $like))
                    ->orWhereHas('batches', fn ($b) => $b->where('serial_number', $term)->where('qty_remaining', '>', 0))
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like));
            });
        }
        foreach (['part_type', 'condition_grade', 'brand_id', 'part_category_id'] as $col) {
            if (! empty($f[$col])) {
                $q->where($col, $f[$col]);
            }
        }
        if (! empty($f['in_stock'])) {
            $q->whereHas('stockLevels', fn ($s) => $s->where('qty_on_hand', '>', 0));
        }
        if (! empty($f['warehouse_id'])) {
            $q->whereHas('stockLevels', fn ($s) => $s->where('warehouse_id', $f['warehouse_id']));
        }

        // vehicle compatibility
        $make = $f['make_id'] ?? null;
        $model = $f['model_id'] ?? null;
        $year = $f['year'] ?? null;
        $vin = strtoupper(trim((string) ($f['vin'] ?? '')));
        if ($vin) {
            $vehicle = Vehicle::query()->where('vin', $vin)->first();
            if ($vehicle) {
                $make = $make ?: $vehicle->vehicle_make_id;
                $model = $model ?: $vehicle->vehicle_model_id;
                $year = $year ?: $vehicle->year;
            }
        }
        if ($make || $model || $year || $vin) {
            $q->whereHas('compatibilities', function ($c) use ($make, $model, $year, $vin) {
                $c->where(function ($w) use ($make, $model, $year, $vin) {
                    $w->where(function ($x) use ($make, $model, $year) {
                        if ($make) {
                            $x->where('vehicle_make_id', $make);
                        }
                        if ($model) {
                            $x->where(fn ($m) => $m->where('vehicle_model_id', $model)->orWhereNull('vehicle_model_id'));
                        }
                        if ($year) {
                            $x->where(fn ($y) => $y->whereNull('year_from')->orWhere('year_from', '<=', $year))
                                ->where(fn ($y) => $y->whereNull('year_to')->orWhere('year_to', '>=', $year));
                        }
                    });
                    if ($vin && strlen($vin) >= 8) {
                        // VIN pattern match on WMI + VDS prefix
                        $w->orWhere(fn ($p) => $p->whereNotNull('vin_pattern')->whereRaw('? LIKE CONCAT(vin_pattern, \'%\')', [$vin]));
                    }
                });
            });
        }

        return $q->orderBy('name_ar');
    }
}
