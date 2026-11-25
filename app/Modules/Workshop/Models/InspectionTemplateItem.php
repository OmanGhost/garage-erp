<?php

namespace App\Modules\Workshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionTemplateItem extends Model
{
    protected $guarded = ['id'];


    public function template(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\InspectionTemplate::class, 'inspection_template_id');
    }
}
