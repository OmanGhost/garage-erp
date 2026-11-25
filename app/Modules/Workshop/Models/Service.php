<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends TenantModel
{
    use SoftDeletes;

    protected $casts = [
        'price' => 'decimal:3',
        'hourly_rate' => 'decimal:3',
        'estimated_cost' => 'decimal:3',
        'commission_value' => 'decimal:3',
        'tax_rate' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Department::class);
    }

    public function vehiclePrices(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\ServiceVehiclePrice::class);
    }

    public function displayName(): string
    {
        return local_name($this);
    }

    /** Price for a vehicle type (falls back to base price / hourly estimate). */
    public function priceFor(?string $vehicleType = null): string
    {
        if ($vehicleType) {
            $p = $this->vehiclePrices->firstWhere('vehicle_type', $vehicleType);
            if ($p) {
                return (string) $p->price;
            }
        }
        if ($this->pricing_type === 'hourly') {
            return \App\Support\Money::mul($this->hourly_rate, \App\Support\Money::div($this->estimated_minutes, 60, 4));
        }

        return (string) $this->price;
    }
}
