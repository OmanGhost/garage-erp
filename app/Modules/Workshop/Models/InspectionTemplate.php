<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionTemplate extends TenantModel
{
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\InspectionTemplateItem::class);
    }
}
