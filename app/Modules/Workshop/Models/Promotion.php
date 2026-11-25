<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;

class Promotion extends TenantModel
{
    protected $casts = [
        'value' => 'decimal:3',
        'min_amount' => 'decimal:3',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];
}
