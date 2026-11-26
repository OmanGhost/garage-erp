<?php

namespace App\Modules\Saas\Models;

use App\Models\User;
use App\Modules\Core\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A client's request for a custom domain: register a new domain or connect one it already owns (platform level). */
class DomainRequest extends Model
{
    public const TYPES = ['register', 'connect'];

    public const STATUSES = ['new', 'processing', 'completed', 'rejected', 'cancelled'];

    protected $guarded = ['id'];

    protected $casts = ['handled_at' => 'datetime', 'price' => 'decimal:3'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['new', 'processing'], true);
    }
}
