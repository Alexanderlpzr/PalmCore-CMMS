<?php

namespace App\Policies;

use App\Models\ProductionCalendarDay;
use App\Models\User;

/**
 * Las horas programadas son el denominador de la eficiencia de planta. Quien las
 * escribe decide contra qué se mide todo el mes.
 */
class ProductionCalendarDayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('production-calendar.view');
    }

    public function view(User $user, ProductionCalendarDay $day): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('production-calendar.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('production-calendar.manage');
    }

    public function update(User $user, ProductionCalendarDay $day): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('production-calendar.manage');
    }

    public function delete(User $user, ProductionCalendarDay $day): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('production-calendar.manage');
    }
}
