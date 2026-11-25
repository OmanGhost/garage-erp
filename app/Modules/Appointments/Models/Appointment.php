<?php

namespace App\Modules\Appointments\Models;

use App\Models\TenantModel;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends TenantModel
{
    use BelongsToBranch;

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Department::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Service::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'technician_id');
    }

    public function bay(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\WorkshopBay::class, 'bay_id');
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
