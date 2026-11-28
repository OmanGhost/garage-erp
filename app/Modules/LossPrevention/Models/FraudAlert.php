<?php

namespace App\Modules\LossPrevention\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A suspicious operation found by the loss-prevention rules, waiting for a manager's review. */
class FraudAlert extends TenantModel
{
    public const RULES = ['high_discount', 'below_cost', 'price_override', 'price_change', 'payment_cancelled', 'credit_note', 'void_sale',
        'stock_write_off', 'frequent_adjustments', 'part_adjustments', 'unbilled_parts', 'cash_shortage', 'cash_overage', 'after_hours'];

    protected $casts = ['details' => 'array', 'amount' => 'decimal:3', 'last_seen_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Link to the record behind the alert, when there is a screen for it. */
    public function subjectUrl(): ?string
    {
        $id = $this->subject_id;

        return match ($this->subject_type) {
            'invoice' => \Illuminate\Support\Facades\Route::has('invoices.show') && \App\Modules\Sales\Models\Invoice::query()->whereKey($id)->exists() ? route('invoices.show', $id) : null,
            'job_card' => route('job-cards.show', $id),
            'pos_session' => route('pos.sessions.show', $id),
            'payment' => route('crud.show', ['payments', $id]),
            'credit_note' => route('crud.show', ['credit-notes', $id]),
            'stock_adjustment' => route('crud.show', ['stock-adjustments', $id]),
            'part' => route('crud.show', ['parts', $id]),
            default => null,
        };
    }
}
