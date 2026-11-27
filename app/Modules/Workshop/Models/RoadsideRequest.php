<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Roadside assistance call (tow, battery, tyre, fuel…): dispatched to a driver, priced by distance, optionally covered by a membership. */
class RoadsideRequest extends TenantModel
{
    public const TYPES = ['tow', 'battery', 'tyre', 'fuel', 'lockout', 'jumpstart', 'other'];

    public const OPEN = ['new', 'assigned', 'en_route', 'on_site'];

    protected $casts = [
        'lat' => 'float', 'lng' => 'float', 'distance_km' => 'decimal:1', 'covered' => 'boolean',
        'base_fee' => 'decimal:3', 'km_rate' => 'decimal:3', 'extra_charges' => 'decimal:3', 'total' => 'decimal:3',
        'location_at' => 'datetime', 'assigned_at' => 'datetime', 'en_route_at' => 'datetime', 'arrived_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    protected $hidden = ['public_token'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Loyalty\Models\Membership::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\Invoice::class);
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function mapUrl(): ?string
    {
        return $this->lat !== null && $this->lng !== null ? 'https://www.google.com/maps?q='.$this->lat.','.$this->lng : null;
    }

    /** Minutes from the call to arrival on site. */
    public function responseMinutes(): ?int
    {
        return $this->arrived_at ? (int) $this->created_at->diffInMinutes($this->arrived_at) : null;
    }

    public function displayName(): string
    {
        return $this->number;
    }
}
