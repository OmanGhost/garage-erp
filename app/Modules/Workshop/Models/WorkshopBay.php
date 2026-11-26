<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopBay extends TenantModel
{
    protected $casts = [
        'is_active' => 'boolean',
        'has_lift' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Department::class);
    }
}
