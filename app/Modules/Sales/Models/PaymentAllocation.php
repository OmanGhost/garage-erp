<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentAllocation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:3',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\Payment::class);
    }

    public function allocatable(): MorphTo
    {
        return $this->morphTo();
    }
}
