<?php

namespace App\Modules\Sales\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use App\Support\Concerns\LocksWhenPosted;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends TenantModel
{
    use BelongsToBranch, LocksWhenPosted, HasAttachments;

    protected array $lockedStatuses = ['posted', 'partially_paid', 'paid', 'cancelled'];

    protected array $mutableWhenLocked = ['journal_entry_id', 'posted_at', 'posted_by', 'number', 'cost_total'];

    protected $casts = [
        'subtotal' => 'decimal:3',
        'discount_total' => 'decimal:3',
        'tax_total' => 'decimal:3',
        'total' => 'decimal:3',
        'paid_amount' => 'decimal:3',
        'cost_total' => 'decimal:3',
        'issue_date' => 'date',
        'due_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\PosSession::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Sales\Models\InvoiceItem::class);
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(\App\Modules\Sales\Models\PaymentAllocation::class, 'allocatable');
    }

    public function insuranceClaim(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Insurance\Models\InsuranceClaim::class);
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Fleet\Models\Fleet::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'salesperson_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(\App\Modules\Sales\Models\CreditNote::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\JournalEntry::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function balanceDue(): string
    {
        $credited = $this->relationLoaded('creditNotes') ? $this->creditNotes->where('status', 'posted')->sum('total') : $this->creditNotes()->where('status', 'posted')->sum('total');

        return \App\Support\Money::max(0, \App\Support\Money::sub(\App\Support\Money::sub($this->total, $this->paid_amount), $credited));
    }

    public function isPosted(): bool
    {
        return in_array($this->status, ['posted', 'partially_paid', 'paid'], true);
    }

    public function displayNumber(): string
    {
        return $this->number ?? ('DRAFT-'.$this->id);
    }
}
