<?php

namespace App\Support\Tenancy;

use App\Modules\Core\Models\Branch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Restricts operational documents to the branches the user may access and
 * defaults branch_id to the current working branch.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model) {
            if (empty($model->branch_id)) {
                $model->branch_id = app(Tenant::class)->branchId();
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
