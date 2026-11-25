<?php

namespace App\Modules\Insurance\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceClaim extends TenantModel
{
    use BelongsToBranch, HasAttachments;

    protected $casts = [
        'estimated_cost' => 'decimal:3',
        'approved_amount' => 'decimal:3',
        'deductible_amount' => 'decimal:3',
        'accident_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Insurance\Models\InsuranceCompany::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Sales\Models\Invoice::class);
    }
}
