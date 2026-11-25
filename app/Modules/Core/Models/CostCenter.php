<?php

namespace App\Modules\Core\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCenter extends TenantModel
{
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\CostCenter::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(\App\Modules\Core\Models\CostCenter::class, 'parent_id');
    }

    public function displayName(): string
    {
        return local_name($this);
    }
}
