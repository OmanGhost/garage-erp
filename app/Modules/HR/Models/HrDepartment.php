<?php

namespace App\Modules\HR\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrDepartment extends TenantModel
{

    public function manager(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'manager_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(\App\Modules\HR\Models\Employee::class);
    }
}
