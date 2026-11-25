<?php

namespace App\Modules\Accounting\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends TenantModel
{
    use SoftDeletes, HasAttachments;

    protected $casts = [
        'cost' => 'decimal:3',
        'salvage_value' => 'decimal:3',
        'accumulated_depreciation' => 'decimal:3',
        'book_value' => 'decimal:3',
        'disposal_amount' => 'decimal:3',
        'purchase_date' => 'date',
        'depreciation_start' => 'date',
        'disposed_at' => 'date',
    ];

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Account::class, 'asset_account_id');
    }

    public function accumulatedAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Account::class, 'accumulated_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Account::class, 'expense_account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\CostCenter::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Branch::class);
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(\App\Modules\Accounting\Models\AssetDepreciation::class);
    }
}
