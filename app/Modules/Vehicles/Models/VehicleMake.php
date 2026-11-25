<?php

namespace App\Modules\Vehicles\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleMake extends Model
{
    protected $guarded = ['id'];


    public function models(): HasMany
    {
        return $this->hasMany(\App\Modules\Vehicles\Models\VehicleModel::class);
    }
}
