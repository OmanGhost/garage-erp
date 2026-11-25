<?php

namespace App\Modules\Workshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'unit_price' => 'decimal:3',
        'discount' => 'decimal:3',
        'tax_amount' => 'decimal:3',
        'line_total' => 'decimal:3',
        'qty' => 'decimal:3',
        'tax_rate' => 'decimal:3',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Quotation::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Service::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }
}
