<?php

namespace App\Modules\HR\Models;

use App\Models\TenantModel;

class LeaveType extends TenantModel
{
    protected $casts = [
        'is_paid' => 'boolean',
    ];
}
