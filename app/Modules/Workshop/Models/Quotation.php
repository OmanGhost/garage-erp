<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends TenantModel
{
    use SoftDeletes, BelongsToBranch, HasAttachments;

    protected $casts = [
        'subtotal' => 'decimal:3',
        'discount_total' => 'decimal:3',
        'tax_total' => 'decimal:3',
        'total' => 'decimal:3',
        'approved_total' => 'decimal:3',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
        'is_supplementary' => 'boolean',
    ];

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
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Inspection::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Quotation::class, 'parent_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\Quotation::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\QuotationItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent'], true);
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast() && in_array($this->status, ['draft', 'sent'], true);
    }
}
