<?php

namespace App\Modules\Saas\Models;

use App\Models\User;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A free-trial request submitted from the public website (platform level, not tenant scoped). */
class TrialRequest extends Model
{
    public const STATUSES = ['new', 'contacted', 'provisioned', 'rejected'];

    public const BUSINESS_TYPES = ['workshop', 'parts', 'both', 'dismantling', 'fleet'];

    protected $guarded = ['id'];

    protected $casts = ['handled_at' => 'datetime', 'checked_at' => 'datetime', 'meta' => 'array'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** Number of OTHER requests sharing this email, phone or IP address. */
    public function duplicates(): array
    {
        $q = fn (string $col, ?string $v) => $v ? static::query()->whereKeyNot($this->getKey())->where($col, $v)->count() : 0;

        return ['email' => $q('email', $this->email), 'phone' => $q('phone', $this->phone), 'ip' => $q('ip_address', $this->ip_address)];
    }
}
