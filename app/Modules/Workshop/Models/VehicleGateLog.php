<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleGateLog extends TenantModel
{
    use BelongsToBranch;

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(HandoverAuthorization::class, 'handover_authorization_id');
    }

    /** GI-000123 (entry) / GO-000123 (exit). */
    public function reference(): string
    {
        return ($this->direction === 'in' ? 'GI-' : 'GO-').str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
