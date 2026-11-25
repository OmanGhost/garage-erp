<?php

namespace App\Modules\Workshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobCardPart extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'unit_price' => 'decimal:3',
        'discount' => 'decimal:3',
        'total_cost' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'qty' => 'decimal:3',
        'qty_reserved' => 'decimal:3',
        'qty_issued' => 'decimal:3',
        'qty_returned' => 'decimal:3',
        'tax_rate' => 'decimal:3',
        'issued_at' => 'datetime',
        'is_additional' => 'boolean',
    ];

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\QuotationItem::class);
    }

    public function issuedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'issued_to');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'issued_by');
    }

    /** Net quantity consumed on the job (issued - returned). */
    public function netQty(): string
    {
        return \App\Support\Money::sub($this->qty_issued, $this->qty_returned);
    }
}
