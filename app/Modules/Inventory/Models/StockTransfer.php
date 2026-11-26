<?php

namespace App\Modules\Inventory\Models;

use App\Models\TenantModel;
use App\Support\Concerns\LocksWhenPosted;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends TenantModel
{
    use LocksWhenPosted;

    protected array $lockedStatuses = ['received', 'cancelled'];

    protected $casts = [
        'transfer_date' => 'date',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\StockTransferItem::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class, 'to_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sent_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'received_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'cancelled_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(StockTransferEvent::class)->latest('id');
    }

    /** Add a step to the activity log. */
    public function logEvent(string $event, ?string $notes = null, array $data = []): StockTransferEvent
    {
        return $this->events()->create(['event' => $event, 'notes' => $notes, 'data' => $data ?: null, 'user_id' => auth()->id()]);
    }
}
