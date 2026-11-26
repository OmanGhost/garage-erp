<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartAlternative extends Model
{
    protected $guarded = ['id'];


    public function alternativePart(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class, 'alternative_part_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }
}
