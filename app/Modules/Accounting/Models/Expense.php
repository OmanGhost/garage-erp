<?php

namespace App\Modules\Accounting\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use App\Support\Concerns\LocksWhenPosted;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends TenantModel
{
    use BelongsToBranch, LocksWhenPosted, HasAttachments;

    protected array $lockedStatuses = ['posted'];

    protected array $mutableWhenLocked = ['journal_entry_id'];

    protected $casts = [
        'amount' => 'decimal:3',
        'tax_amount' => 'decimal:3',
        'total' => 'decimal:3',
        'date' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Account::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\BankAccount::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\CostCenter::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\JournalEntry::class, 'journal_entry_id');
    }
}
