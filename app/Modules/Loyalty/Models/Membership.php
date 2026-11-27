<?php

namespace App\Modules\Loyalty\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A customer's membership: active once its invoice is posted, with a balance of included services. */
class Membership extends TenantModel
{
    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date', 'price' => 'decimal:3'];

    public function plan(): BelongsTo { return $this->belongsTo(MembershipPlan::class, 'membership_plan_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(\App\Modules\CRM\Models\Customer::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(\App\Modules\Vehicles\Models\Vehicle::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(\App\Modules\Sales\Models\Invoice::class); }
    public function balances(): HasMany { return $this->hasMany(MembershipBalance::class); }
    public function usages(): HasMany { return $this->hasMany(MembershipUsage::class); }

    public function isActive(): bool
    {
        return $this->status === 'active' && (! $this->ends_at || $this->ends_at->gte(today()));
    }

    public function roadsideLeft(): int
    {
        return max(0, (int) ($this->plan?->roadside_calls ?? 0) - (int) $this->roadside_used);
    }
}
