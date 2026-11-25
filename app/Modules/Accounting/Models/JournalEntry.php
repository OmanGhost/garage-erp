<?php

namespace App\Modules\Accounting\Models;

use App\Models\TenantModel;
use App\Support\Concerns\LocksWhenPosted;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends TenantModel
{
    use LocksWhenPosted;

    protected array $lockedStatuses = ['posted', 'reversed'];

    protected array $mutableWhenLocked = ['reversed_by_id'];

    protected $casts = [
        'total_debit' => 'decimal:3',
        'total_credit' => 'decimal:3',
        'date' => 'date',
        'posted_at' => 'datetime',
        'is_opening' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(\App\Modules\Accounting\Models\JournalLine::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Branch::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\JournalEntry::class, 'reversal_of_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\JournalEntry::class, 'reversed_by_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'posted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
