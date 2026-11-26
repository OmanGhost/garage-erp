<?php

namespace App\Modules\Sales\Models;

use App\Models\TenantModel;
use App\Support\Concerns\LocksWhenPosted;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends TenantModel
{
    use BelongsToBranch, LocksWhenPosted;

    protected array $lockedStatuses = ['posted'];

    protected array $mutableWhenLocked = ['journal_entry_id', 'posted_at', 'posted_by', 'number', 'refunded_amount', 'refund_payment_id'];

    protected $casts = [
        'subtotal' => 'decimal:3',
        'tax_total' => 'decimal:3',
        'total' => 'decimal:3',
        'cost_total' => 'decimal:3',
        'refunded_amount' => 'decimal:3',
        'date' => 'date',
        'posted_at' => 'datetime',
        'restock' => 'boolean',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\Sales\Models\CreditNoteItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\JournalEntry::class, 'journal_entry_id');
    }

    public function refundPayment(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\Payment::class, 'refund_payment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'posted_by');
    }

    public function displayNumber(): string
    {
        return $this->number ?? 'DRAFT-'.$this->id;
    }

    /** Part of the credit that stays on the customer account (not paid back in cash / bank). */
    public function creditedToAccount(): string
    {
        return \App\Support\Money::sub($this->total, $this->refunded_amount ?? 0);
    }
}
