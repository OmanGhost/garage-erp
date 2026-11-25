<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCostLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:3',
    ];

    public function landedCost(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\LandedCost::class);
    }
}
