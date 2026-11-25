<?php

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'total_cost' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'qty' => 'decimal:3',
        'balance_after' => 'decimal:3',
        'moved_at' => 'datetime',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\StockBatch::class, 'batch_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
