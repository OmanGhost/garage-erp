<?php

namespace App\Modules\Inventory\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends TenantModel
{
    use SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
        'is_consignment' => 'boolean',
        'is_virtual' => 'boolean',
    ];

    public function consignor(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class, 'consignor_supplier_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Branch::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\WarehouseLocation::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\StockLevel::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'warehouse_user');
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'en' && $this->name_en ? $this->name_en : $this->name;
    }
}
