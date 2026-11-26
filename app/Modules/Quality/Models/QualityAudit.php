<?php

namespace App\Modules\Quality\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Internal quality audit: checklist answered conform / nonconform / observation; findings open NCRs. */
class QualityAudit extends TenantModel
{
    use HasAttachments;

    protected $casts = ['planned_date' => 'date', 'performed_at' => 'datetime', 'checklist' => 'array', 'score' => 'decimal:1'];

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'auditor_id');
    }

    public function nonconformities(): HasMany
    {
        return $this->hasMany(Nonconformity::class);
    }
}
