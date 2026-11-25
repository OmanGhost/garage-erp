<?php

namespace App\Modules\Store\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreOrder extends TenantModel
{
    protected $casts = [
        'shipping_fee' => 'decimal:3',
        'subtotal' => 'decimal:3',
        'discount_total' => 'decimal:3',
        'tax_total' => 'decimal:3',
        'total' => 'decimal:3',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Store\Models\Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Store\Models\StoreOrderItem::class);
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
