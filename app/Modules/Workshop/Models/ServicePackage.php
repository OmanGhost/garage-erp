<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePackage extends TenantModel
{
    protected $casts = [
        'price' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\ServicePackageItem::class);
    }

    public function displayName(): string
    {
        return local_name($this);
    }
}
