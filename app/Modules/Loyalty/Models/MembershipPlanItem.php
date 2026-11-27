<?php

namespace App\Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipPlanItem extends Model
{
    protected $guarded = ['id'];

    public function service(): BelongsTo { return $this->belongsTo(\App\Modules\Workshop\Models\Service::class); }
}
