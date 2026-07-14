<?php

namespace App\Domain\Platform\Services;

use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Tenant;
use App\Models\User;

/**
 * Cortar y devolver el acceso de una empresa.
 *
 * Suspender no borra nada: la empresa deja de entrar, pero sus equipos, sus órdenes y
 * su histórico siguen exactamente donde estaban. Es una llave, no una demolición — y
 * por eso reactivar la devuelve entera.
 */
class TenantAccessService
{
    public function suspend(Tenant $tenant): Tenant
    {
        if ($tenant->subscription_status === SubscriptionStatus::Suspended) {
            throw new BusinessRuleException('Esta empresa ya está suspendida.');
        }

        $tenant->update([
            'subscription_status' => SubscriptionStatus::Suspended->value,
            'is_active' => false,
        ]);

        return $tenant->refresh();
    }

    public function reactivate(Tenant $tenant): Tenant
    {
        if ($tenant->subscription_status !== SubscriptionStatus::Suspended) {
            throw new BusinessRuleException('Esta empresa no está suspendida.');
        }

        $tenant->update([
            'subscription_status' => SubscriptionStatus::Active->value,
            'is_active' => true,
        ]);

        return $tenant->refresh();
    }

    /**
     * El dueño de la empresa: a quien tiene sentido suplantar cuando el cliente llama
     * diciendo «no me deja hacer X». Si no hay dueño marcado, no se adivina.
     */
    public function owner(Tenant $tenant): ?User
    {
        return $tenant->users()
            ->wherePivot('is_owner', true)
            ->where('users.is_active', true)
            ->first();
    }
}
