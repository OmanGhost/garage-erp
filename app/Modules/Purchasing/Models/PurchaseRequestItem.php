<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'estimated_cost' => 'decimal:3',
        'qty' => 'decimal:3',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\PurchaseRequest::class, 'purchase_request_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }
}
