<?php

namespace App\Modules\CRM\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry of a complaint's tracking history (created, status change, assignment, note, resolution). */
class ComplaintUpdate extends TenantModel
{
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
