<?php

namespace App\Modules\Fleet\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fleet extends TenantModel
{
    protected $casts = [
        'monthly_spend_limit' => 'decimal:3',
        'per_job_limit' => 'decimal:3',
        'discount_percent' => 'decimal:3',
        'contract_start' => 'date',
        'contract_end' => 'date',
        'requires_approval' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function jobCards(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(\App\Modules\Fleet\Models\FleetApproval::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Sales\Models\Invoice::class);
    }
}
