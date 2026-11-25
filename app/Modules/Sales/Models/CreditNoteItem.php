<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'unit_price' => 'decimal:3',
        'tax_amount' => 'decimal:3',
        'line_total' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'qty' => 'decimal:3',
        'tax_rate' => 'decimal:3',
    ];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\CreditNote::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\InvoiceItem::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }
}
