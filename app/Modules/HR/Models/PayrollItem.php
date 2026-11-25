<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'basic' => 'decimal:3',
        'allowances' => 'decimal:3',
        'overtime' => 'decimal:3',
        'commissions' => 'decimal:3',
        'bonuses' => 'decimal:3',
        'deductions' => 'decimal:3',
        'loan_deduction' => 'decimal:3',
        'absence_deduction' => 'decimal:3',
        'gross' => 'decimal:3',
        'net' => 'decimal:3',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\HR\Models\Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\HR\Models\Employee::class);
    }
}
