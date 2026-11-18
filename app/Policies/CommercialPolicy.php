<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo comercial —clientes, transportadoras, ofertas mercantiles y despachos—: verlo pide
 * commercial.view y registrarlo o corregirlo, commercial.manage. Los dos los lleva el rol
 * «direccion-administrativa». Nada se borra para siempre: los despachos son la cartera.
 */
abstract class CommercialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('commercial.view');
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('commercial.manage');
    }

    public function update(User $user, Model $record): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->create($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->create($user);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->create($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->create($user);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
