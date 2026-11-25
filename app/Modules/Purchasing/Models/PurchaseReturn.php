<?php

namespace App\Modules\Purchasing\Models;

use App\Models\TenantModel;
use App\Support\Concerns\LocksWhenPosted;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends TenantModel
{
    use BelongsToBranch, LocksWhenPosted;

    protected array $lockedStatuses = ['posted'];

    protected array $mutableWhenLocked = ['journal_entry_id', 'posted_at'];

    protected $casts = [
        'subtotal' => 'decimal:3',
        'tax_total' => 'decimal:3',
        'total' => 'decimal:3',
        'date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\GoodsReceipt::class, 'goods_receipt_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\PurchaseReturnItem::class);
    }
}
