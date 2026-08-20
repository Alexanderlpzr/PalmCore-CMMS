<?php

namespace App\Policies;

use App\Models\EnergyMeter;
use App\Models\User;

/**
 * Quién lee y quién escribe el consumo de energía.
 *
 * Se separa ver de gestionar porque son dos personas distintas: el operario que hace la
 * ronda anota las lecturas, y gerencia lee el indicador sin tocar nada.
 */
class EnergyMeterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('energy.view');
    }

    public function view(User $user, EnergyMeter $meter): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('energy.manage');
    }

    public function update(User $user, EnergyMeter $meter): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, EnergyMeter $meter): bool
    {
        return $this->create($user);
    }
}
