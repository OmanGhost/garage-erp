<?php

namespace App\Modules\Core\Models;

use App\Models\TenantModel;

class NotificationTemplate extends TenantModel
{
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
