<?php

namespace App\Modules\Workshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionTemplateItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['min_ok' => 'decimal:3', 'max_ok' => 'decimal:3'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Service::class);
    }

    /** Result for a measured value: ok inside the limits, attention outside (urgent when far outside: 20 %). */
    public function judge(mixed $value): ?string
    {
        if ($value === null || $value === '' || ($this->min_ok === null && $this->max_ok === null)) {
            return null;
        }
        $v = (float) $value;
        if ($this->min_ok !== null && $v < (float) $this->min_ok) {
            return $v < (float) $this->min_ok * 0.8 ? 'urgent' : 'attention';
        }
        if ($this->max_ok !== null && $v > (float) $this->max_ok) {
            return $v > (float) $this->max_ok * 1.2 ? 'urgent' : 'attention';
        }

        return 'ok';
    }


    public function template(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\InspectionTemplate::class, 'inspection_template_id');
    }
}
