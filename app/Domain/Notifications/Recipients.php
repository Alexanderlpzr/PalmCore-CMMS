<?php

namespace App\Domain\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A quién le llega un aviso: los usuarios activos de una empresa con alguno de esos roles.
 *
 * Pregunta por el rol con la empresa explícita (roles.team_id) y no por la relación
 * `roles` de Spatie, que filtra por la empresa «actual»: en un job o un comando no hay
 * empresa actual, y la consulta volvía vacía sin error. Así se quedaban sin aviso el
 * almacén con el stock crítico o mantenimiento con el MTBF.
 */
class Recipients
{
    /**
     * @param  list<string>  $roles
     * @return Collection<int, User>
     */
    public static function withRoles(string $tenantId, array $roles, ?User $except = null): Collection
    {
        $userIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.team_id', $tenantId)
            ->whereIn('roles.name', array_values(array_unique($roles)))
            ->pluck('model_has_roles.'.config('permission.column_names.model_morph_key', 'model_id'));

        return User::withoutGlobalScopes()
            ->whereIn('id', $userIds)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get();
    }
}
