<?php

namespace App\Modules\Sales\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Core charge: refundable deposit collected with a part until the customer brings the old part back. */
class CoreReturn extends TenantModel
{
    protected $casts = ['qty' => 'decimal:3', 'amount' => 'decimal:3', 'due_date' => 'date', 'returned_at' => 'datetime'];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function customer(): BelongsTo { return $this->belongsTo(\App\Modules\CRM\Models\Customer::class); }
    public function part(): BelongsTo { return $this->belongsTo(\App\Modules\Inventory\Models\Part::class); }
    public function creditNote(): BelongsTo { return $this->belongsTo(CreditNote::class); }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date && $this->due_date->lt(today());
    }
}
