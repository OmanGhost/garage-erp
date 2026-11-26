<?php

namespace App\Modules\Quality\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nonconformity report (NCR): what went wrong, root cause and the corrective / preventive actions (CAPA). */
class Nonconformity extends TenantModel
{
    use HasAttachments;

    public const SOURCES = ['job_card', 'comeback', 'qc_fail', 'supplier', 'part', 'internal_audit', 'customer_complaint', 'warranty', 'internal'];

    public const CATEGORIES = ['workmanship', 'part_defect', 'procedure', 'equipment', 'communication', 'supplier', 'other'];

    public const STATUSES = ['open', 'investigating', 'action', 'verification', 'closed'];

    protected $casts = ['detected_at' => 'date', 'closed_at' => 'datetime', 'cost' => 'decimal:3'];

    public function actions(): HasMany
    {
        return $this->hasMany(CapaAction::class)->orderBy('id');
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(QualityAudit::class, 'quality_audit_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'technician_id');
    }

    public function detector(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'detected_by');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'responsible_id');
    }
}
