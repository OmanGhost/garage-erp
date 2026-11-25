<?php

namespace App\Modules\Workshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionResult extends Model
{
    protected $guarded = ['id'];


    public function inspection(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Inspection::class);
    }
}
