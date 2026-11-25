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

    protected $casts = ['handled_at' => 'datetime'];

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
}
