<?php

namespace App\Domain\Platform\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Poner o reemplazar la contraseña de un usuario desde la plataforma.
 *
 * Las contraseñas se guardan con un hash que no se puede revertir: nadie —ni el
 * superadministrador ni quien administra el servidor— puede verlas. Lo que sí se puede
 * es reemplazarlas, y eso es lo que hace este servicio.
 *
 * Al reemplazarla se cierran todas las sesiones de esa persona y se revocan sus tokens
 * de la app móvil. Si alguien pide un cambio de contraseña porque sospecha que otro
 * entró con la suya, dejar abierta la sesión de ese otro no resolvería nada.
 */
class UserPasswordService
{
    /**
     * @param  bool  $mustChange  true si la persona debe reemplazarla por una propia al entrar.
     */
    public function setPassword(User $user, #[SensitiveParameter] string $password, bool $mustChange): void
    {
        DB::transaction(function () use ($user, $password, $mustChange): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'must_change_password' => $mustChange,
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            $this->signOutEverywhere($user);
        });
    }

    /**
     * Una contraseña temporal aleatoria, que la persona tendrá que cambiar al entrar.
     *
     * Legible a propósito —sin 0/O ni 1/l— porque alguien la va a dictar por teléfono.
     *
     * @return string La contraseña en claro. Se muestra una vez y no se guarda en ningún sitio.
     */
    public function generateTemporary(User $user): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $password = '';

        for ($i = 0; $i < 12; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $this->setPassword($user, $password, mustChange: true);

        return $password;
    }

    /** La persona eligió su propia contraseña: deja de ser temporal. */
    public function markChangedByOwner(User $user): void
    {
        $user->forceFill([
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();
    }

    private function signOutEverywhere(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }

        $user->tokens()->delete();
    }
}
