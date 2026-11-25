<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:3',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'trial_ends_at' => 'date',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Core\Models\SubscriptionInvoice::class);
    }
}
