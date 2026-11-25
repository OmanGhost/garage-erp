<?php

namespace App\Modules\Purchasing\Models;

use App\Models\TenantModel;
use App\Support\Concerns\LocksWhenPosted;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandedCost extends TenantModel
{
    use LocksWhenPosted;

    protected array $lockedStatuses = ['posted'];

    protected array $mutableWhenLocked = ['journal_entry_id', 'posted_at'];

    protected $casts = [
        'total' => 'decimal:3',
        'date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\GoodsReceipt::class, 'goods_receipt_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\LandedCostLine::class);
    }
}
