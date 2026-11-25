<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $ids = app(Tenant::class)->branchIds();
        if ($ids !== null) {
            $col = $model->qualifyColumn('branch_id');
            $builder->where(fn ($q) => $q->whereIn($col, $ids ?: [0])->orWhereNull($col));
        }
    }
}
