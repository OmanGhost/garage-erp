<?php

namespace App\Modules\Fleet\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetApproval extends TenantModel
{
    protected $casts = [
        'amount' => 'decimal:3',
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Fleet\Models\Fleet::class);
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Quotation::class);
    }
}
