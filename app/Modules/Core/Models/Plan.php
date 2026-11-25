<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price_monthly' => 'decimal:3',
        'price_yearly' => 'decimal:3',
        'is_active' => 'boolean',
        'modules' => 'array',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(\App\Modules\Core\Models\Company::class);
    }
}
