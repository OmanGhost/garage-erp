<?php

namespace App\Modules\Warranty\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warranty extends TenantModel
{
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'supplier_warranty_end' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\Invoice::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\InvoiceItem::class);
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Service::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(\App\Modules\Warranty\Models\WarrantyClaim::class);
    }

    /** Valid when within date range and (if limited) within the km allowance. */
    public function isValid(?int $odometer = null, $on = null): bool
    {
        $on = $on ? \Illuminate\Support\Carbon::parse($on) : now();
        if ($this->status === 'void' || $on->lt($this->start_date) || $on->gt($this->end_date->copy()->endOfDay())) {
            return false;
        }
        if ($this->km_limit && $odometer !== null && $this->start_odometer !== null) {
            return ($odometer - $this->start_odometer) <= $this->km_limit;
        }

        return true;
    }
}
