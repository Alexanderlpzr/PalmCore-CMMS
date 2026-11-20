<?php

namespace App\Http\Responses;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * A dónde se va después de iniciar sesión.
 *
 * El superadministrador entra a su panel de plataforma —empresas, usuarios, salud del
 * sistema— y desde ahí escoge a qué empresa entrar. Antes caía directo en la primera
 * empresa, porque el panel por defecto es el de las empresas (pedido 2026-11-19).
 *
 * Si venía de un enlace a una pantalla concreta (un aviso, una OT), se le lleva ahí: solo
 * la entrada «en blanco» —la raíz del sitio o la de un panel— va a la plataforma. Los
 * demás usuarios siguen como siempre.
 */
class LoginResponse implements Responsable
{
    /** Las direcciones que no dicen a dónde se quería ir. */
    private const BARE_PATHS = ['', '/', '/admin', '/platform'];

    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_super_admin) {
            return redirect()->intended(Filament::getUrl());
        }

        $intended = session()->pull('url.intended');
        $path = $intended === null ? '' : rtrim((string) parse_url($intended, PHP_URL_PATH), '/');

        if ($intended !== null && ! in_array($path, self::BARE_PATHS, true)) {
            return redirect()->to($intended);
        }

        return redirect()->to(Filament::getPanel('platform')->getUrl());
    }
}
