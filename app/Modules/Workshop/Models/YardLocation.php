<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A place in the workshop yard where a vehicle can stand (parking spot, bay, wash, waiting area…). */
class YardLocation extends TenantModel
{
    public const TYPES = ['parking', 'bay', 'wash', 'waiting', 'road_test', 'outside'];

    protected $casts = ['is_active' => 'boolean'];

    public function bay(): BelongsTo
    {
        return $this->belongsTo(WorkshopBay::class, 'bay_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Branch::class);
    }

    public function displayName(): string
    {
        return $this->code.' — '.$this->name.($this->zone ? ' ('.$this->zone.')' : '');
    }
}
