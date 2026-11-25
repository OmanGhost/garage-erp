<?php

namespace App\Modules\Purchasing\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rfq extends TenantModel
{
    protected $casts = [
        'due_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\RfqItem::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\RfqSupplier::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\PurchaseRequest::class);
    }

    public function awardedSupplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class, 'awarded_supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
