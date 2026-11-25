<?php

namespace App\Modules\Inventory\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Part extends TenantModel
{
    use SoftDeletes, HasAttachments;

    protected $casts = [
        'sale_price' => 'decimal:3',
        'wholesale_price' => 'decimal:3',
        'workshop_price' => 'decimal:3',
        'min_sale_price' => 'decimal:3',
        'purchase_price' => 'decimal:4',
        'average_cost' => 'decimal:4',
        'min_stock' => 'decimal:3',
        'reorder_qty' => 'decimal:3',
        'max_stock' => 'decimal:3',
        'profit_margin' => 'decimal:3',
        'tax_rate' => 'decimal:3',
        'track_serial' => 'boolean',
        'track_batch' => 'boolean',
        'track_expiry' => 'boolean',
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'is_service_item' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\PartCategory::class, 'part_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Brand::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Purchasing\Models\Supplier::class, 'default_supplier_id');
    }

    public function alternatives(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\PartAlternative::class);
    }

    public function compatibilities(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\PartCompatibility::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\StockLevel::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\StockBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\StockMovement::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(\App\Modules\Purchasing\Models\Supplier::class, 'part_supplier');
    }

    public function displayName(): string
    {
        return local_name($this);
    }

    public const TYPES = ['genuine', 'oem', 'aftermarket', 'used', 'refurbished', 'damaged', 'scrap'];
    public const GRADES = ['A', 'B', 'C', 'damaged'];

    public function label(): string
    {
        return $this->sku.' — '.$this->displayName();
    }

    public function onHand(?int $warehouseId = null): string
    {
        $q = $this->stockLevels();
        if ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        }

        return (string) $q->sum('qty_on_hand');
    }

    public function available(?int $warehouseId = null): string
    {
        $q = $this->stockLevels();
        if ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        }

        return \App\Support\Money::sub($q->sum('qty_on_hand'), $q->sum('qty_reserved'));
    }

    /** Selling price for a price level (retail|wholesale|workshop). */
    public function priceFor(string $level = 'retail'): string
    {
        return match ($level) {
            'wholesale' => (string) ($this->wholesale_price > 0 ? $this->wholesale_price : $this->sale_price),
            'workshop' => (string) ($this->workshop_price > 0 ? $this->workshop_price : $this->sale_price),
            default => (string) $this->sale_price,
        };
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path) : null;
    }
}
