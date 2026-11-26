<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Movement of a vehicle in the yard or of its key (append-only history). */
class VehicleTrackingEvent extends TenantModel
{
    public const UPDATED_AT = null;

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(YardLocation::class, 'yard_location_id');
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'holder_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
