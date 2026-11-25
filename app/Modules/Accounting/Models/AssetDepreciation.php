<?php

namespace App\Modules\Accounting\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciation extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:3',
        'period_date' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\FixedAsset::class, 'fixed_asset_id');
    }
}
