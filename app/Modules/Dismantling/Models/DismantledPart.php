<?php

namespace App\Modules\Dismantling\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DismantledPart extends TenantModel
{
    use HasAttachments;

    protected $casts = [
        'estimated_value' => 'decimal:3',
        'sale_price' => 'decimal:3',
        'allocated_cost' => 'decimal:3',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Dismantling\Models\DismantledVehicle::class, 'dismantled_vehicle_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\WarehouseLocation::class, 'location_id');
    }
}
