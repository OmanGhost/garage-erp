<?php

namespace App\Modules\Quality\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Corrective or preventive action of a nonconformity, with its effectiveness check. */
class CapaAction extends TenantModel
{
    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime', 'verified_at' => 'datetime', 'effective' => 'boolean'];

    public function nonconformity(): BelongsTo
    {
        return $this->belongsTo(Nonconformity::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'verified_by');
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['open', 'in_progress'], true) && $this->due_date && $this->due_date->lt(today());
    }
}
