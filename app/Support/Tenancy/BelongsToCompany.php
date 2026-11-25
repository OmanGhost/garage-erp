<?php

namespace App\Support\Tenancy;

use App\Modules\Core\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isolates a model per company: automatic global scope + automatic company_id.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            if (empty($model->company_id)) {
                $id = app(Tenant::class)->id();
                if ($id === null) {
                    throw new \LogicException('Cannot create '.static::class.' without a tenant context.');
                }
                $model->company_id = $id;
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('company_id') && $model->getOriginal('company_id') !== null) {
                throw new \LogicException('company_id is immutable.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
