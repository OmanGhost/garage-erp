<?php

namespace App\Modules\Quality\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Standard operating procedure (work instruction) with version, owner and review date. */
class QualitySop extends TenantModel
{
    use HasAttachments, SoftDeletes;

    protected $casts = ['effective_date' => 'date', 'review_date' => 'date'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Department::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function reviewDue(): bool
    {
        return $this->status === 'active' && $this->review_date && $this->review_date->lte(today()->addDays(30));
    }
}
