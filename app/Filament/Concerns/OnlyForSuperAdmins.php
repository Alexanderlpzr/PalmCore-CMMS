<?php

namespace App\Filament\Concerns;

/**
 * Reserva una pantalla del panel de tenant al super administrador.
 *
 * Se engancha en `canAccess()` y no en `shouldRegisterNavigation()` a propósito:
 * Filament consulta `canAccess()` tanto para pintar el enlace en el menú como
 * para dejar entrar por la URL (ver HasNavigation). Ocultar solo el enlace
 * dejaría la pantalla accesible a quien escribiera la dirección a mano, que es
 * seguridad de escaparate.
 *
 * Es para las pantallas que administra el proveedor, no la planta: usuarios,
 * roles, permisos, reglas de automatización y la auditoría de datos. La planta
 * no las necesita para operar y equivocarse en ellas rompe cosas que el
 * ingeniero de mantenimiento no puede diagnosticar.
 */
trait OnlyForSuperAdmins
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }
}
