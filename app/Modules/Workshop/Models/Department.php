<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends TenantModel
{
    use SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'supervisor_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\CostCenter::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Branch::class);
    }

    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'department_user');
    }

    public function services(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\Service::class);
    }

    public function displayName(): string
    {
        return local_name($this);
    }
}
