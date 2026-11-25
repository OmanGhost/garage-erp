<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'unit_cost' => 'decimal:4',
        'system_qty' => 'decimal:3',
        'counted_qty' => 'decimal:3',
        'difference' => 'decimal:3',
    ];

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\StockCount::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }
}
