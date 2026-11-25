<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'qty' => 'decimal:3',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Rfq::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }
}
