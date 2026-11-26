<?php

namespace App\Modules\Inventory\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step of a stock transfer's activity: requested, dispatched, received, cancelled, note, printed. */
class StockTransferEvent extends TenantModel
{
    protected $casts = ['data' => 'array'];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
