<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Road-test permit for a vehicle in the workshop: who drives, who authorised it, odometer before and after. */
class TestDrive extends TenantModel
{
    protected $casts = [
        'customer_consent' => 'boolean',
        'out_at' => 'datetime',
        'in_at' => 'datetime',
    ];

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'driver_id');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'authorized_by');
    }

    public function distance(): ?int
    {
        return $this->odometer_in !== null && $this->odometer_out !== null ? max(0, $this->odometer_in - $this->odometer_out) : null;
    }

    public function overLimit(): bool
    {
        return $this->max_km && ($this->distance() ?? 0) > $this->max_km;
    }
}
