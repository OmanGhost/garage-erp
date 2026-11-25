<?php

namespace App\Modules\Vehicles\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleModel extends Model
{
    protected $guarded = ['id'];


    public function make(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\VehicleMake::class, 'vehicle_make_id');
    }
}
