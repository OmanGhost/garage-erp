<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends TenantModel
{
    use HasAttachments;

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\InspectionTemplate::class, 'inspection_template_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'technician_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\InspectionResult::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\InspectionFinding::class);
    }
}
