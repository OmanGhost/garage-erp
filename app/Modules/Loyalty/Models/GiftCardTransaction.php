<?php

namespace App\Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCardTransaction extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:3'];
}
