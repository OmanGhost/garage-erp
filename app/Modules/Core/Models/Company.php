<?php

namespace App\Modules\Core\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Tenant (subscriber company).
 */
class Company extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'settings' => 'array',
        'enabled_modules' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'vat_rate' => 'decimal:3',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function displayName(): string
    {
        return (app()->getLocale() === 'ar' ? ($this->name_ar ?: $this->name) : ($this->name_en ?: $this->name));
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /** Module activation: core modules always on; otherwise company override → plan → all. */
    public function moduleEnabled(string $module): bool
    {
        $def = config('erp.modules.'.$module);
        if (! $def) {
            return false;
        }
        if (! empty($def['core'])) {
            return true;
        }
        if (is_array($this->enabled_modules)) {
            return in_array($module, $this->enabled_modules, true);
        }
        if ($this->plan && is_array($this->plan->modules)) {
            return in_array($module, $this->plan->modules, true);
        }

        return true;
    }

    public function subscriptionActive(): bool
    {
        if (! $this->is_active || in_array($this->subscription_status, ['suspended', 'cancelled'], true)) {
            return false;
        }
        if ($this->subscription_status === 'trial') {
            return ! $this->trial_ends_at || $this->trial_ends_at->isFuture();
        }

        // 7-day grace period after expiry
        return ! $this->subscription_ends_at || $this->subscription_ends_at->copy()->addDays(7)->isFuture();
    }
}
