<?php

namespace App\Modules\HR\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoan extends TenantModel
{
    protected $casts = [
        'amount' => 'decimal:3',
        'installment' => 'decimal:3',
        'remaining' => 'decimal:3',
        'date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\HR\Models\Employee::class);
    }
}
