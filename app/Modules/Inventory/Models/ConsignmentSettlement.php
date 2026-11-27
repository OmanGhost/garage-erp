<?php

namespace App\Modules\Inventory\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What was sold from a consignment warehouse in a period and billed by the consignor. */
class ConsignmentSettlement extends TenantModel
{
    protected $casts = ['period_from' => 'datetime', 'period_to' => 'datetime', 'qty' => 'decimal:3', 'amount' => 'decimal:3'];

    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class); }
    public function supplierInvoice(): BelongsTo { return $this->belongsTo(\App\Modules\Purchasing\Models\SupplierInvoice::class); }
}
