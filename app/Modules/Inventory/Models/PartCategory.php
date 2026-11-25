<?php

namespace App\Modules\Inventory\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartCategory extends TenantModel
{

    public function parent(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\PartCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\PartCategory::class, 'parent_id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\Part::class);
    }

    public function displayName(): string
    {
        return local_name($this);
    }
}
