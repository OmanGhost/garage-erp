<?php

namespace App\Modules\Purchasing\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierEvaluation extends TenantModel
{
    protected $casts = [
        'overall' => 'decimal:3',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'evaluated_by');
    }
}
