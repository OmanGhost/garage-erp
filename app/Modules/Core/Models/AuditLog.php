<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    /** The audit trail is append-only: rows are written by AuditLogger and never changed or removed by the application. */
    protected static function booted(): void
    {
        static::updating(fn () => throw new \App\Exceptions\BusinessException(__('Audit log records cannot be changed.')));
        static::deleting(fn () => throw new \App\Exceptions\BusinessException(__('Audit log records cannot be deleted.')));
    }

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
