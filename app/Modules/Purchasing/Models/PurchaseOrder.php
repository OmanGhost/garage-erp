<?php

namespace App\Modules\Purchasing\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends TenantModel
{
    use SoftDeletes, BelongsToBranch, HasAttachments;

    protected $casts = [
        'subtotal' => 'decimal:3',
        'discount_total' => 'decimal:3',
        'tax_total' => 'decimal:3',
        'total' => 'decimal:3',
        'order_date' => 'date',
        'expected_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\GoodsReceipt::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\SupplierInvoice::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Rfq::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\PurchaseRequest::class);
    }
}
