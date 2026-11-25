<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = app(Tenant::class)->id();
        if ($id !== null) {
            $builder->where($model->qualifyColumn('company_id'), $id);
        }
    }
}
