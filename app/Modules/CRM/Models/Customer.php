<?php

namespace App\Modules\CRM\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends TenantModel
{
    use SoftDeletes, HasAttachments;

    protected $casts = [
        'loyalty_points' => 'decimal:3',
        'credit_limit' => 'decimal:3',
        'discount_percent' => 'decimal:3',
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'marketing_opt_in' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\CustomerGroup::class, 'customer_group_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(\App\Modules\CRM\Models\CustomerContact::class);
    }

    public function jobCards(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Sales\Models\Invoice::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\Quotation::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(\App\Modules\CRM\Models\Complaint::class);
    }

    public function portalUser(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'customer_id');
    }

    public function fleets(): HasMany
    {
        return $this->hasMany(\App\Modules\Fleet\Models\Fleet::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(\App\Modules\Appointments\Models\Appointment::class);
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(\App\Modules\Warranty\Models\Warranty::class);
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'en' && $this->name_en ? $this->name_en : $this->name;
    }

    /** Receivable balance from the general ledger (debit - credit on AR lines). */
    public function balance(): string
    {
        return \App\Modules\Accounting\Services\Ledger::partyBalance('customer', $this->id);
    }

    public function availableCredit(): string
    {
        return \App\Support\Money::sub($this->credit_limit, $this->balance());
    }

    /** The shared walk-in / cash customer of the POS (never earns, redeems or refers). */
    public function isWalkIn(): bool
    {
        return $this->code === 'WALKIN' || (int) setting('pos.walk_in_customer_id') === (int) $this->id;
    }

    public function referrals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'referred_by_id');
    }

    public function referrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_id');
    }

    public function memberships(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\Loyalty\Models\Membership::class);
    }
}
