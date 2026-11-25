<?php

namespace App\Modules\Assets\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentLog extends TenantModel
{
    protected $casts = [
        'cost' => 'decimal:3',
        'date' => 'date',
        'next_due_date' => 'date',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Assets\Models\Equipment::class);
    }
}
