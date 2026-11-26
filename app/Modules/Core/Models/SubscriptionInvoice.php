<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Platform (SaaS) tax invoice to a client company: subscription renewals and custom charges. */
class SubscriptionInvoice extends Model
{
    public const STATUSES = ['unpaid', 'paid', 'cancelled'];

    public const METHODS = ['bank_transfer', 'cash', 'card', 'cheque', 'online'];

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:3',
        'subtotal' => 'decimal:3',
        'discount' => 'decimal:3',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:3',
        'items' => 'array',
        'issue_date' => 'date',
        'due_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Company::class)->withTrashed();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Subscription::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'unpaid' && $this->due_date && $this->due_date->endOfDay()->isPast();
    }

    /** unpaid | overdue | paid | cancelled */
    public function stage(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }
}
