<?php

namespace App\Modules\Purchasing\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends TenantModel
{
    use SoftDeletes, HasAttachments;

    protected $casts = [
        'credit_limit' => 'decimal:3',
        'rating' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\PurchaseOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\SupplierInvoice::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\SupplierEvaluation::class);
    }

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(\App\Modules\Inventory\Models\Part::class, 'part_supplier');
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'en' && $this->name_en ? $this->name_en : $this->name;
    }

    /** Payable balance (credit - debit on AP lines). */
    public function balance(): string
    {
        return \App\Support\Money::neg(\App\Modules\Accounting\Services\Ledger::partyBalance('supplier', $this->id));
    }
}
