<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqSupplier extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'total' => 'decimal:3',
        'quoted_at' => 'datetime',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Rfq::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(\App\Modules\Purchasing\Models\RfqQuote::class);
    }
}
