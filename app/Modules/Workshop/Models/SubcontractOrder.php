<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Work sent to an outside specialist (machining, transmission, ECU…) for a job card. The customer's part leaves without a stock movement. */
class SubcontractOrder extends TenantModel
{
    public const WORK_TYPES = ['machining', 'transmission', 'ecu', 'radiator', 'upholstery', 'paint', 'electrical', 'other'];

    protected $casts = [
        'quoted_cost' => 'decimal:3', 'actual_cost' => 'decimal:3', 'sell_price' => 'decimal:3',
        'expected_at' => 'date', 'warranty_until' => 'date', 'sent_at' => 'datetime', 'returned_at' => 'datetime',
    ];

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(JobCardTask::class, 'job_card_task_id');
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\SupplierInvoice::class);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'sent_out' && $this->expected_at && $this->expected_at->lt(today());
    }

    public function underWarranty(): bool
    {
        return $this->warranty_until && $this->warranty_until->gte(today());
    }

    public function displayName(): string
    {
        return $this->number;
    }
}
