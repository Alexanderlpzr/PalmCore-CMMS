<?php

namespace App\Filament\Concerns;

use App\Models\Equipment;

/**
 * Reserva una pantalla a quien trabaja en mantenimiento: la que muestra indicadores de
 * planta, alertas de equipos o configuración técnica.
 *
 * Existe por el rol `talento-humano`. Estas pantallas no preguntaban nada —cualquiera
 * con sesión en el tenant entraba— y eso no se notaba mientras todos los usuarios eran
 * de mantenimiento. Con una cuenta de RRHH que no debe ver equipos, el Dashboard le
 * habría mostrado los paros, los costos y el presupuesto de la planta.
 *
 * La pregunta es «¿ve los equipos?» porque es la puerta de entrada de todo lo técnico:
 * quien no ve un equipo no tiene por qué ver sus indicadores. Se engancha en
 * `canAccess()`, que Filament consulta para el menú y para la URL (ver
 * {@see OnlyForSuperAdmins}).
 */
trait RequiresMaintenanceAccess
{
    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Equipment::class) ?? false;
    }
}
