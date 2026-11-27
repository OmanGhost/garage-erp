<?php

namespace App\Modules\Sales\Models;

use App\Models\TenantModel;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends TenantModel
{
    use BelongsToBranch;

    protected $casts = [
        'subtotal' => 'decimal:3',
        'discount_total' => 'decimal:3',
        'tax_total' => 'decimal:3',
        'total' => 'decimal:3',
        'order_date' => 'date',
        'delivery_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Quotation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Sales\Models\SalesOrderItem::class);
    }

    public function dropShipOrder(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Modules\Purchasing\Models\PurchaseOrder::class)->where('drop_ship', true)->whereNotIn('status', ['cancelled', 'rejected']);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\Invoice::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }
}
