<?php

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBatch extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'unit_cost' => 'decimal:4',
        'qty_received' => 'decimal:3',
        'qty_remaining' => 'decimal:3',
        'expiry_date' => 'date',
        'received_at' => 'datetime',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }
}
