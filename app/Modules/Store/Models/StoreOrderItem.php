<?php

namespace App\Modules\Store\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreOrderItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'unit_price' => 'decimal:3',
        'tax_amount' => 'decimal:3',
        'line_total' => 'decimal:3',
        'qty' => 'decimal:3',
        'tax_rate' => 'decimal:3',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Store\Models\StoreOrder::class, 'store_order_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }
}
