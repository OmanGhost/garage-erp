<?php

namespace App\Modules\HR\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeContract extends TenantModel
{
    use HasAttachments;

    protected $casts = [
        'basic_salary' => 'decimal:3',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\HR\Models\Employee::class);
    }
}
