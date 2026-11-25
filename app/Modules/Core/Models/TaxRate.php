<?php

namespace App\Modules\Core\Models;

use App\Models\TenantModel;

class TaxRate extends TenantModel
{
    protected $casts = [
        'rate' => 'decimal:3',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}
