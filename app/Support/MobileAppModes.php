<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;

/**
 * Qué partes de la app móvil le corresponden a quien inicia sesión.
 *
 * La app pide esto al entrar y al renovar la sesión para saber adónde llevar a cada
 * uno: el vigilante abre directo en la portería y no ve mantenimiento, que no podría
 * consultar. Los permisos de verdad los sigue aplicando el servidor en cada petición;
 * esto solo decide qué se muestra.
 */
final class MobileAppModes
{
    /** Lo que la app pide al emitir o renovar el token: lo de mantenimiento y lo de la puerta. */
    public const TOKEN_ABILITIES = [
        'work-orders.read', 'work-orders.write', 'equipment.read',
        'maintenance-requests.read', 'maintenance-requests.write',
        'inventory.read', 'plants.read', 'areas.read', 'reliability.read',
        'attendance.read', 'attendance.write',
    ];

    /**
     * @return array{maintenance: bool, gate: bool}
     */
    public static function for(User $user, Tenant $tenant): array
    {
        if ($user->is_super_admin) {
            return ['maintenance' => true, 'gate' => true];
        }

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId($tenant->id);
        // Spatie guarda en la relación los roles del equipo con que se cargaron.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return [
                'maintenance' => $user->checkPermissionTo('work-orders.view'),
                'gate' => $user->checkPermissionTo('attendance.record'),
            ];
        } finally {
            setPermissionsTeamId($previousTeam);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
