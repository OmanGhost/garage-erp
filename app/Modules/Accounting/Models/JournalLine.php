<?php

namespace App\Modules\Accounting\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalLine extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'debit' => 'decimal:3',
        'credit' => 'decimal:3',
        'reconciled_at' => 'datetime',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Account::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\CostCenter::class);
    }

    public function party(): MorphTo
    {
        return $this->morphTo();
    }
}
