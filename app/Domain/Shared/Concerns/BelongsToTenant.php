<?php

namespace App\Domain\Shared\Concerns;

use App\Infrastructure\Tenancy\CurrentTenant;
use App\Infrastructure\Tenancy\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $model): void {
            if (empty($model->tenant_id) && CurrentTenant::isSet()) {
                $model->tenant_id = CurrentTenant::id();
            }
        });
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where($this->getTable().'.tenant_id', $tenantId);
    }

    public function scopeAcrossAllTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
