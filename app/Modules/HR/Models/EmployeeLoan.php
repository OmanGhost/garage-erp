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
        'profit_rate' => 'decimal:3',
        'profit_amount' => 'decimal:3',
        'total_payable' => 'decimal:3',
        'profit_collected' => 'decimal:3',
        'date' => 'date',
        'first_month' => 'date',
    ];

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeLoanPayment::class)->orderBy('date')->orderBy('id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\HR\Models\Employee::class);
    }
}
