<?php

namespace App\Modules\Sales\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends TenantModel
{
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Branch::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\BankAccount::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(\App\Modules\Sales\Models\PosSession::class);
    }
}
