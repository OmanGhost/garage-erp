<?php

namespace App\Modules\Accounting\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends TenantModel
{
    protected $casts = [
        'statement_balance' => 'decimal:3',
        'book_balance' => 'decimal:3',
        'difference' => 'decimal:3',
        'statement_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\BankAccount::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(\App\Modules\Accounting\Models\JournalLine::class, 'bank_reconciliation_id');
    }
}
