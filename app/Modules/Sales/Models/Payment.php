<?php

namespace App\Modules\Sales\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use App\Support\Concerns\LocksWhenPosted;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends TenantModel
{
    use BelongsToBranch, LocksWhenPosted, HasAttachments;

    protected array $lockedStatuses = ['posted', 'cancelled'];

    protected array $mutableWhenLocked = ['journal_entry_id'];

    protected $casts = [
        'amount' => 'decimal:3',
        'date' => 'date',
        'cheque_date' => 'date',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\BankAccount::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(\App\Modules\Sales\Models\PaymentAllocation::class);
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\PosSession::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\JournalEntry::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    public function counterAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Account::class, 'counter_account_id');
    }
}
