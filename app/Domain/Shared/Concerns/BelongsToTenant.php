<?php

namespace App\Domain\Shared\Concerns;

use App\Infrastructure\Tenancy\CurrentTenant;
use App\Infrastructure\Tenancy\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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
