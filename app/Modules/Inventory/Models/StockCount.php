<?php

namespace App\Modules\Inventory\Models;

use App\Models\TenantModel;
use App\Support\Concerns\LocksWhenPosted;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends TenantModel
{
    use LocksWhenPosted;

    protected array $lockedStatuses = ['posted', 'cancelled'];

    protected array $mutableWhenLocked = ['journal_entry_id', 'posted_at', 'posted_by'];

    protected $casts = [
        'count_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\StockCountItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
