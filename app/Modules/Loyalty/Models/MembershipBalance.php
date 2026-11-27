<?php

namespace App\Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipBalance extends Model
{
    protected $guarded = ['id'];

    public function service(): BelongsTo { return $this->belongsTo(\App\Modules\Workshop\Models\Service::class); }

    public function left(): int
    {
        return max(0, (int) $this->included - (int) $this->used);
    }
}
