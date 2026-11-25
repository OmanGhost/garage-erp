<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'unit_cost' => 'decimal:4',
        'qty' => 'decimal:3',
        'received_qty' => 'decimal:3',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\StockTransfer::class, 'stock_transfer_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }
}
