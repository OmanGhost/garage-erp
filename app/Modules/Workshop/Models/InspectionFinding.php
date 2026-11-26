<?php

namespace App\Modules\Workshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionFinding extends Model
{
    use \App\Support\Concerns\HasAttachments;

    protected $guarded = ['id'];

    protected $casts = [
        'estimated_price' => 'decimal:3',
        'qty' => 'decimal:3',
        'decided_at' => 'datetime',
    ];

    public function light(): string
    {
        return Inspection::light($this->severity);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Inspection::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Service::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Part::class);
    }
}
