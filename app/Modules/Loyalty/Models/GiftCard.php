<?php

namespace App\Modules\Loyalty\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Prepaid gift card: sold for cash (liability), spent as a payment on invoices. */
class GiftCard extends TenantModel
{
    protected $casts = ['initial_amount' => 'decimal:3', 'balance' => 'decimal:3', 'expires_at' => 'date'];

    public function customer(): BelongsTo { return $this->belongsTo(\App\Modules\CRM\Models\Customer::class); }
    public function transactions(): HasMany { return $this->hasMany(GiftCardTransaction::class)->latest('id'); }

    public function usable(): bool
    {
        return $this->status === 'active' && (float) $this->balance > 0 && (! $this->expires_at || $this->expires_at->gte(today()));
    }
}
