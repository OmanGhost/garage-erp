<?php

namespace App\Modules\Store\Models;

use App\Models\TenantModel;

class Coupon extends TenantModel
{
    protected $casts = [
        'value' => 'decimal:3',
        'min_order' => 'decimal:3',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];
}
