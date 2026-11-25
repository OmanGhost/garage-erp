<?php

namespace App\Modules\Accounting\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends TenantModel
{

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\FiscalYear::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(\App\Modules\Accounting\Models\BudgetLine::class);
    }
}
