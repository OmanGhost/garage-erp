<?php

namespace App\Modules\Assets\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends TenantModel
{
    use SoftDeletes, HasAttachments;

    protected $table = 'equipment';

    protected $casts = [
        'purchase_cost' => 'decimal:3',
        'operating_cost_per_hour' => 'decimal:3',
        'purchase_date' => 'date',
        'warranty_until' => 'date',
        'next_maintenance_at' => 'date',
        'next_calibration_at' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Branch::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\HR\Models\Employee::class, 'custodian_employee_id');
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\FixedAsset::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(\App\Modules\Assets\Models\EquipmentLog::class);
    }
}
