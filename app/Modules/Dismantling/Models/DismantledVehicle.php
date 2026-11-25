<?php

namespace App\Modules\Dismantling\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DismantledVehicle extends TenantModel
{
    use SoftDeletes, BelongsToBranch, HasAttachments;

    protected $casts = [
        'purchase_cost' => 'decimal:3',
        'transport_cost' => 'decimal:3',
        'dismantling_cost' => 'decimal:3',
        'other_costs' => 'decimal:3',
        'allocated_cost' => 'decimal:3',
        'scrap_revenue' => 'decimal:3',
        'purchase_date' => 'date',
    ];

    public function make(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\VehicleMake::class, 'vehicle_make_id');
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\VehicleModel::class, 'vehicle_model_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(\App\Modules\Dismantling\Models\DismantledPart::class);
    }

    public function scraps(): HasMany
    {
        return $this->hasMany(\App\Modules\Dismantling\Models\DismantledScrap::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\BankAccount::class, 'paid_from_bank_account_id');
    }

    public function totalCost(): string
    {
        return \App\Support\Money::add($this->purchase_cost, $this->transport_cost, $this->dismantling_cost, $this->other_costs);
    }

    public function title(): string
    {
        return trim(($this->make?->name ?? '').' '.($this->vehicleModel?->name ?? '').' '.($this->year ?? ''));
    }
}
