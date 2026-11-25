<?php

namespace App\Modules\Dismantling\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DismantledScrap extends TenantModel
{
    protected $casts = [
        'amount' => 'decimal:3',
        'date' => 'date',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Dismantling\Models\DismantledVehicle::class, 'dismantled_vehicle_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\BankAccount::class);
    }
}
