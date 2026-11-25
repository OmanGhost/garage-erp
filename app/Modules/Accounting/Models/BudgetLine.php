<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:3',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Budget::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Account::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\CostCenter::class);
    }
}
