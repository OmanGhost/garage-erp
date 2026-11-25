<?php

namespace App\Modules\CRM\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerGroup extends TenantModel
{
    protected $casts = [
        'discount_percent' => 'decimal:3',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(\App\Modules\CRM\Models\Customer::class);
    }
}
