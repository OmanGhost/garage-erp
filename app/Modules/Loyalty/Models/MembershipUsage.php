<?php

namespace App\Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipUsage extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['qty' => 'decimal:3', 'value' => 'decimal:3'];

    public function service(): BelongsTo { return $this->belongsTo(\App\Modules\Workshop\Models\Service::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(\App\Modules\Sales\Models\Invoice::class); }
}
