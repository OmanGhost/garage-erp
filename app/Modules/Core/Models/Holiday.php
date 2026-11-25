<?php

namespace App\Modules\Core\Models;

use App\Models\TenantModel;

class Holiday extends TenantModel
{
    protected $casts = [
        'date' => 'date',
        'end_date' => 'date',
        'is_recurring' => 'boolean',
    ];
}
