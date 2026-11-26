<?php

namespace App\Modules\CRM\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer complaint / feedback. Gets a reference number (CMP-YYYY-000001) on creation and keeps a tracking
 * history (complaint_updates): creation, status changes, (re)assignment, resolution and staff notes.
 */
class Complaint extends TenantModel
{
    use HasAttachments;

    public const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Complaint $c) {
            $c->number ??= DocumentNumber::next('complaint');
        });
        static::created(function (Complaint $c) {
            $c->track('created', null, $c->status, $c->description ? \Illuminate\Support\Str::limit($c->description, 250) : null);
        });
        static::updated(function (Complaint $c) {
            if ($c->wasChanged('status')) {
                $c->track('status', $c->getOriginal('status'), $c->status);
            }
            if ($c->wasChanged('assigned_to')) {
                $c->track('assigned', null, null, $c->assignee()->value('name'));
            }
            if ($c->wasChanged('resolution') && filled($c->resolution)) {
                $c->track('resolution', null, null, \Illuminate\Support\Str::limit($c->resolution, 500));
            }
        });
    }

    public function track(string $type, ?string $from = null, ?string $to = null, ?string $note = null): ComplaintUpdate
    {
        return $this->updates()->create(['type' => $type, 'from_status' => $from, 'to_status' => $to, 'note' => $note, 'user_id' => auth()->id()]);
    }

    public function displayNumber(): string
    {
        return $this->number ?: 'CMP-'.$this->id;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ComplaintUpdate::class)->latest('id');
    }
}
