<?php

namespace App\Modules\Vehicles\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends TenantModel
{
    use SoftDeletes, HasAttachments;

    protected $casts = [
        'insurance_expiry' => 'date',
        'registration_expiry' => 'date',
        'next_service_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Fleet\Models\Fleet::class);
    }

    public function make(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\VehicleMake::class, 'vehicle_make_id');
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\VehicleModel::class, 'vehicle_model_id');
    }

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Insurance\Models\InsuranceCompany::class);
    }

    public function jobCards(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(\App\Modules\Vehicles\Models\VehicleOwnership::class);
    }

    public function accidents(): HasMany
    {
        return $this->hasMany(\App\Modules\Vehicles\Models\VehicleAccident::class);
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(\App\Modules\Warranty\Models\Warranty::class);
    }

    public function plate(): string
    {
        return trim(($this->plate_number ?? '').' '.($this->plate_code ?? '')) ?: ($this->vin ?? '-');
    }

    public function title(): string
    {
        return trim(($this->make?->name ?? '').' '.($this->vehicleModel?->name ?? '').' '.($this->year ?? ''));
    }

    public function label(): string
    {
        return $this->plate().' — '.$this->title();
    }
}
