<?php

namespace App\Modules\Core\Models;

use App\Models\TenantModel;

class Integration extends TenantModel
{
    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'credentials' => 'encrypted:array',
    ];
}
