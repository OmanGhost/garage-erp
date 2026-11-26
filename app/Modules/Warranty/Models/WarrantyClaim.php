<?php

namespace App\Modules\Warranty\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaim extends TenantModel
{
    use HasAttachments;

    protected $casts = [
        'cost' => 'decimal:3',
        'supplier_recovered_amount' => 'decimal:3',
        'claim_date' => 'date',
        'supplier_claim' => 'boolean',
    ];

    protected static function booted(): void
    {
        // activity log: opened, status changes (with the inspection result) and supplier claim updates
        static::created(fn (WarrantyClaim $c) => $c->logEvent('opened', null, $c->status, $c->description));
        static::updated(function (WarrantyClaim $c) {
            if ($c->wasChanged('status')) {
                $c->logEvent('status', $c->getOriginal('status'), $c->status, $c->inspection_result);
            }
            if ($c->wasChanged(['supplier_claim_status', 'supplier_recovered_amount'])) {
                $c->logEvent('supplier', null, $c->supplier_claim_status, __('Recovered from supplier').': '.money($c->supplier_recovered_amount));
            }
        });
    }

    public function logEvent(string $event, ?string $from = null, ?string $to = null, ?string $notes = null): WarrantyClaimEvent
    {
        return $this->events()->create(['event' => $event, 'from_status' => $from, 'to_status' => $to, 'notes' => $notes, 'user_id' => auth()->id()]);
    }

    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WarrantyClaimEvent::class)->latest('id');
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Warranty\Models\Warranty::class);
    }

    public function reworkJobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class, 'rework_job_card_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
