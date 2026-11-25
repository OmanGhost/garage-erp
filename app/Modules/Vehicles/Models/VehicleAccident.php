<?php

namespace App\Modules\Vehicles\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleAccident extends TenantModel
{
    use HasAttachments;

    protected $casts = [
        'accident_date' => 'date',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Insurance\Models\InsuranceClaim::class, 'insurance_claim_id');
    }
}
