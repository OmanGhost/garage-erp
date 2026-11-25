<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqQuote extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'unit_price' => 'decimal:4',
    ];

    public function rfqSupplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\RfqSupplier::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\RfqItem::class, 'rfq_item_id');
    }
}
