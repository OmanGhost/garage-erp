<?php

namespace App\Modules\Insurance\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceCompany extends TenantModel
{
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(\App\Modules\Insurance\Models\InsuranceClaim::class);
    }
}
