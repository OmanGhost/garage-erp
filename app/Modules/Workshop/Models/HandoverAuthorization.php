<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The owner of a vehicle authorises another person to drop it off at / pick it up from the workshop.
 *
 * Life cycle: pending (issued) → active (owner approved by code or signature) → used (single use, after the
 * handover) | revoked (by staff on the owner's request). An active authorization outside its validity window
 * is reported as "expired" (effectiveStatus) and cannot be used.
 */
class HandoverAuthorization extends TenantModel
{
    public const TYPES = ['drop_off', 'pick_up', 'both'];

    public const ID_TYPES = ['civil_id', 'resident_card', 'passport', 'driving_license'];

    public const RELATIONSHIPS = ['driver', 'employee', 'family', 'friend', 'company_rep', 'other'];

    protected $casts = [
        'valid_from' => 'datetime', 'valid_until' => 'datetime', 'single_use' => 'boolean', 'code_sent_at' => 'datetime',
        'approved_at' => 'datetime', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime',
    ];

    protected $hidden = ['approval_code_hash'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'revoked_by');
    }

    public function isValidNow(): bool
    {
        return $this->valid_from->lte(now()) && (! $this->valid_until || $this->valid_until->gte(now()));
    }

    /** pending | active | used | revoked | expired | not_started */
    public function effectiveStatus(): string
    {
        if ($this->status === 'active') {
            if ($this->valid_until && $this->valid_until->lt(now())) {
                return 'expired';
            }
            if ($this->valid_from->gt(now())) {
                return 'not_started';
            }
        }

        return $this->status;
    }

    public function allows(string $direction): bool
    {
        return $this->type === 'both' || $this->type === ($direction === 'in' ? 'drop_off' : 'pick_up');
    }

    /** Active, inside the validity window, for this vehicle (and job card when it is tied to one) and direction. */
    public function scopeUsableFor(Builder $q, JobCard $jc, string $direction): Builder
    {
        return $q->where('vehicle_id', $jc->vehicle_id)->where('status', 'active')
            ->where('valid_from', '<=', now())
            ->where(fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->where(fn ($w) => $w->whereNull('job_card_id')->orWhere('job_card_id', $jc->id))
            ->whereIn('type', ['both', $direction === 'in' ? 'drop_off' : 'pick_up']);
    }

    public function label(): string
    {
        return $this->number.' — '.$this->person_name.' ('.$this->id_number.')';
    }
}
