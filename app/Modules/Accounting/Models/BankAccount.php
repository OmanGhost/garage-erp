<?php

namespace App\Modules\Accounting\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends TenantModel
{
    protected $casts = [
        'is_active' => 'boolean',
        'card_fee_percent' => 'decimal:3',
        'card_fee_max' => 'decimal:3',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Account::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Branch::class);
    }

    /**
     * Commission the bank deducts from a card payment: amount × rate, capped at the maximum per transaction.
     * Applies to card payments, and to anything received into a card terminal account.
     */
    public function cardFee(mixed $amount, ?string $method = 'card'): string
    {
        $rate = (string) ($this->card_fee_percent ?? 0);
        if (! \App\Support\Money::isPositive($rate) || ($method !== 'card' && $this->type !== 'card')) {
            return '0';
        }
        $fee = \App\Support\Money::percent($amount, $rate);
        $max = (string) ($this->card_fee_max ?? 0);

        return \App\Support\Money::isPositive($max) ? \App\Support\Money::min($fee, $max) : $fee;
    }
}
