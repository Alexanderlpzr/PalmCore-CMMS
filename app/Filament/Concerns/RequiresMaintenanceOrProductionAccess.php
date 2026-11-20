<?php

namespace App\Filament\Concerns;

use App\Models\Equipment;
use App\Models\User;

/**
 * Los indicadores de planta —Dashboard, paros, eficiencia, productividad—: los ve quien
 * trabaja en mantenimiento ({@see RequiresMaintenanceAccess}) y, solo para consultar, la
 * Dirección de Producción (production.view), que no ve los equipos ni sus órdenes.
 * Estas pantallas no guardan nada por sí mismas; lo que se registra desde ellas pide su
 * propio permiso.
 */
trait RequiresMaintenanceOrProductionAccess
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->can('viewAny', Equipment::class) || $user->checkPermissionTo('production.view');
    }
}
