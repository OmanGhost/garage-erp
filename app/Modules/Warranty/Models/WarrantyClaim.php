<?php

namespace App\Modules\Warranty\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaim extends TenantModel
{
    use HasAttachments;

    protected $casts = [
        'cost' => 'decimal:3',
        'supplier_recovered_amount' => 'decimal:3',
        'claim_date' => 'date',
        'supplier_claim' => 'boolean',
    ];

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Warranty\Models\Warranty::class);
    }

    public function reworkJobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class, 'rework_job_card_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
