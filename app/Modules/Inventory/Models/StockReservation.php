<?php

namespace App\Modules\Inventory\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stock held for a customer / job / order until it is sold or released (expires automatically). */
class StockReservation extends TenantModel
{
    protected $casts = ['qty' => 'decimal:3', 'expires_at' => 'date', 'released_at' => 'datetime'];

    public function part(): BelongsTo { return $this->belongsTo(Part::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function customer(): BelongsTo { return $this->belongsTo(\App\Modules\CRM\Models\Customer::class); }
    public function jobCard(): BelongsTo { return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class); }
    public function salesOrder(): BelongsTo { return $this->belongsTo(\App\Modules\Sales\Models\SalesOrder::class); }
    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'reserved_by'); }
}
