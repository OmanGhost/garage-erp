<?php

namespace App\Modules\Purchasing\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use App\Support\Concerns\LocksWhenPosted;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SupplierInvoice extends TenantModel
{
    use BelongsToBranch, LocksWhenPosted, HasAttachments;

    protected array $mutableWhenLocked = ['journal_entry_id', 'posted_at', 'posted_by'];

    protected $casts = [
        'subtotal' => 'decimal:3',
        'discount_total' => 'decimal:3',
        'tax_total' => 'decimal:3',
        'total' => 'decimal:3',
        'paid_amount' => 'decimal:3',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\PurchaseOrder::class, 'purchase_order_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\GoodsReceipt::class, 'goods_receipt_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\SupplierInvoiceItem::class);
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(\App\Modules\Sales\Models\PaymentAllocation::class, 'allocatable');
    }

    public function balanceDue(): string
    {
        return \App\Support\Money::sub($this->total, $this->paid_amount);
    }
}
