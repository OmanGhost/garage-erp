<?php

namespace App\Modules\Loyalty\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Yearly maintenance membership: included services, discounts, priority booking and roadside calls. */
class MembershipPlan extends TenantModel
{
    protected $casts = ['price' => 'decimal:3', 'parts_discount' => 'decimal:2', 'labor_discount' => 'decimal:2', 'priority_booking' => 'boolean', 'is_active' => 'boolean'];

    public function items(): HasMany { return $this->hasMany(MembershipPlanItem::class); }

    public function displayName(): string
    {
        return app()->getLocale() === 'en' && $this->name_en ? $this->name_en : $this->name;
    }
}
