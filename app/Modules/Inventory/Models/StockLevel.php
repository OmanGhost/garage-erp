<?php

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'avg_cost' => 'decimal:4',
        'qty_on_hand' => 'decimal:3',
        'qty_reserved' => 'decimal:3',
        'last_movement_at' => 'datetime',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\WarehouseLocation::class, 'location_id');
    }

    public function available(): string
    {
        return \App\Support\Money::sub($this->qty_on_hand, $this->qty_reserved);
    }
}
