<?php

namespace App\Modules\Sales\Models;

use App\Models\TenantModel;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends TenantModel
{
    use BelongsToBranch;

    protected $casts = [
        'opening_cash' => 'decimal:3',
        'expected_cash' => 'decimal:3',
        'counted_cash' => 'decimal:3',
        'difference' => 'decimal:3',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function register(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\CashRegister::class, 'cash_register_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Sales\Models\Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(\App\Modules\Sales\Models\Payment::class);
    }
}
