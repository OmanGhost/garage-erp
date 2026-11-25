<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'unit_price' => 'decimal:3',
        'discount' => 'decimal:3',
        'tax_amount' => 'decimal:3',
        'line_total' => 'decimal:3',
        'cost_total' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'qty' => 'decimal:3',
        'returned_qty' => 'decimal:3',
        'tax_rate' => 'decimal:3',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\Invoice::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Service::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }
}
