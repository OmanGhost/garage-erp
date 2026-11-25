<?php

namespace App\Modules\CRM\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceReminder extends TenantModel
{
    protected $casts = [
        'due_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\ServicePackage::class, 'service_package_id');
    }
}
