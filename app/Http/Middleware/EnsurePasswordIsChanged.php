<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quien entra con una contraseña temporal no pasa del Perfil hasta que pone la suya.
 *
 * La temporal la conoce también quien se la dio. Mientras siga vigente, la cuenta no es
 * del todo de su dueño, así que el panel no se abre: solo el Perfil, donde se cambia.
 *
 * Las peticiones de Livewire pasan: el propio formulario del Perfil guarda por ahí, y
 * las demás pantallas no llegan a cargarse, así que no hay componente al que hablarle.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->must_change_password || $request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        $profileUrl = Filament::getProfileUrl();

        if ($profileUrl === null || $request->url() === $profileUrl) {
            return $next($request);
        }

        Notification::make()
            ->title('Cambie su contraseña para continuar')
            ->body('Entró con una contraseña temporal. Elija una propia; desde ese momento solo usted la conocerá.')
            ->warning()
            ->persistent()
            ->send();

        return redirect()->to($profileUrl);
    }
}
