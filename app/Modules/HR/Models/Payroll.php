<?php

namespace App\Modules\HR\Models;

use App\Models\TenantModel;
use App\Support\Concerns\LocksWhenPosted;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends TenantModel
{
    use LocksWhenPosted;

    protected array $lockedStatuses = ['posted', 'paid'];

    protected array $mutableWhenLocked = ['journal_entry_id', 'posted_at', 'paid_at'];

    protected $casts = [
        'total_gross' => 'decimal:3',
        'total_deductions' => 'decimal:3',
        'total_net' => 'decimal:3',
        'period_month' => 'date',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\HR\Models\PayrollItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Branch::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }
}
