<?php

namespace App\Modules\Loyalty\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Points ledger of a customer (earn, redeem, reverse, refund, referral, adjust, expire). */
class LoyaltyTransaction extends TenantModel
{
    protected $casts = ['points' => 'decimal:3', 'value' => 'decimal:3'];

    public function customer(): BelongsTo { return $this->belongsTo(\App\Modules\CRM\Models\Customer::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(\App\Modules\Sales\Models\Invoice::class); }
}
