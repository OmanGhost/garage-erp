<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends TenantModel
{
    protected $casts = [
        'base_amount' => 'decimal:3',
        'amount' => 'decimal:3',
        'rate' => 'decimal:3',
        'earned_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\Invoice::class);
    }
}
