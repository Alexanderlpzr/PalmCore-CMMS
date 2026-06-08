<?php

namespace App\Infrastructure\Tenancy\Scopes;

use App\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (CurrentTenant::isSet()) {
            $builder->where($model->getTable().'.tenant_id', CurrentTenant::id());
        }
    }
}
