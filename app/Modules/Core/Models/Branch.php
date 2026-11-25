<?php

namespace App\Modules\Core\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends TenantModel
{
    use SoftDeletes;

    protected $casts = [
        'is_main' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\CostCenter::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\Warehouse::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'branch_user');
    }

    public function displayName(): string
    {
        return local_name($this);
    }
}
