<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartCompatibility extends Model
{
    protected $guarded = ['id'];


    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }

    public function make(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\VehicleMake::class, 'vehicle_make_id');
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\VehicleModel::class, 'vehicle_model_id');
    }
}
