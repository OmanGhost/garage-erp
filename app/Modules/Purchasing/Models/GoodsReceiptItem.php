<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'line_total' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'landed_cost_per_unit' => 'decimal:4',
        'qty' => 'decimal:3',
        'returned_qty' => 'decimal:3',
        'expiry_date' => 'date',
        'batch_ids' => 'array',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\GoodsReceipt::class, 'goods_receipt_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\WarehouseLocation::class, 'location_id');
    }
}
