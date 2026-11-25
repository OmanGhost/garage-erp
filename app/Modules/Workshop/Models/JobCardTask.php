<?php

namespace App\Modules\Workshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobCardTask extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'unit_price' => 'decimal:3',
        'discount' => 'decimal:3',
        'line_total' => 'decimal:3',
        'labor_cost' => 'decimal:3',
        'commission_amount' => 'decimal:3',
        'qty' => 'decimal:3',
        'tax_rate' => 'decimal:3',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'is_additional' => 'boolean',
    ];

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Department::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Service::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'technician_id');
    }

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\QuotationItem::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\JobCardTimeLog::class);
    }
}
