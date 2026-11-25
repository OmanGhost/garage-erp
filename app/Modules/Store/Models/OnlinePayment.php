<?php

namespace App\Modules\Store\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OnlinePayment extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:3',
        'paid_at' => 'datetime',
        'payload' => 'array',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
