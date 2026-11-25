<?php

namespace App\Modules\Purchasing\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use App\Support\Concerns\LocksWhenPosted;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends TenantModel
{
    use BelongsToBranch, LocksWhenPosted, HasAttachments;

    protected array $lockedStatuses = ['posted', 'cancelled'];

    protected array $mutableWhenLocked = ['journal_entry_id', 'posted_at', 'posted_by', 'number'];

    protected $casts = [
        'total' => 'decimal:3',
        'received_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\GoodsReceiptItem::class);
    }

    public function landedCosts(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\LandedCost::class);
    }
}
