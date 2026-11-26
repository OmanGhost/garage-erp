<?php

namespace App\Modules\Workshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionResult extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['value' => 'decimal:3'];

    public function templateItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\InspectionTemplateItem::class, 'template_item_id');
    }


    public function inspection(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Inspection::class);
    }
}
