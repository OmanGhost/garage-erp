<?php

namespace App\Modules\Accounting\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalPeriod extends TenantModel
{
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\FiscalYear::class);
    }
}
