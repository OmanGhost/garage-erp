<?php

namespace App\Modules\HR\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One repayment of an employee loan: from payroll, paid by hand, early settlement or the opening balance. */
class EmployeeLoanPayment extends TenantModel
{
    protected $casts = ['date' => 'date', 'amount' => 'decimal:3', 'principal' => 'decimal:3', 'profit' => 'decimal:3'];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
