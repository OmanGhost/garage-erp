<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:3',
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Subscription::class);
    }
}
