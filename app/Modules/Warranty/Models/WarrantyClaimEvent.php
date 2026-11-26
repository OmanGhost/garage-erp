<?php

namespace App\Modules\Warranty\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry of a warranty claim's activity: opened, status change, supplier claim update, note. */
class WarrantyClaimEvent extends TenantModel
{
    public function claim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class, 'warranty_claim_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
