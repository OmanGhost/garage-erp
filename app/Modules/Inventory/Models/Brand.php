<?php

namespace App\Modules\Inventory\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends TenantModel
{
    protected $casts = [
        'is_oem' => 'boolean',
    ];

    public function parts(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\Part::class);
    }
}
