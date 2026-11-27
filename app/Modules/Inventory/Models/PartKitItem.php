<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Component of a kit part (e.g. a service kit = filter + oil + washer). */
class PartKitItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['qty' => 'decimal:3'];

    public function component(): BelongsTo { return $this->belongsTo(Part::class, 'component_part_id'); }
    public function kit(): BelongsTo { return $this->belongsTo(Part::class, 'kit_part_id'); }
}
