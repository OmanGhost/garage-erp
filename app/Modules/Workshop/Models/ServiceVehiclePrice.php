<?php

namespace App\Modules\Workshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceVehiclePrice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:3',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Service::class);
    }
}
