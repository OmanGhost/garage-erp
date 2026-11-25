<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'tax_amount' => 'decimal:3',
        'line_total' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'qty' => 'decimal:3',
        'tax_rate' => 'decimal:3',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\SupplierInvoice::class, 'supplier_invoice_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Account::class);
    }
}
