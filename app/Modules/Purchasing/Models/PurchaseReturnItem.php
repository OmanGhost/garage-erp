<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'tax_amount' => 'decimal:3',
        'line_total' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'qty' => 'decimal:3',
        'tax_rate' => 'decimal:3',
    ];

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\PurchaseReturn::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\GoodsReceiptItem::class, 'goods_receipt_item_id');
    }
}
