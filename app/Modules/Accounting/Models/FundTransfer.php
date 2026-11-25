<?php

namespace App\Modules\Accounting\Models;

use App\Models\TenantModel;
use App\Support\Concerns\LocksWhenPosted;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundTransfer extends TenantModel
{
    use LocksWhenPosted;

    protected array $lockedStatuses = ['posted'];

    protected array $mutableWhenLocked = ['journal_entry_id'];

    protected $casts = [
        'amount' => 'decimal:3',
        'date' => 'date',
    ];

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\BankAccount::class, 'from_bank_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\BankAccount::class, 'to_bank_account_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\JournalEntry::class, 'journal_entry_id');
    }
}
