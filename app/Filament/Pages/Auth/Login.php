<?php

namespace App\Filament\Pages\Auth;

use App\Models\LoginBackgroundImage;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Database\Eloquent\Collection;

class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    protected static string $layout = 'filament.pages.auth.login-layout';

    /**
     * @return Collection<int, LoginBackgroundImage>
     */
    public function getBackgroundImages(): Collection
    {
        return LoginBackgroundImage::visible()->get();
    }

    /**
     * El verde del logo de Fronda, no el violeta propio del panel de plataforma:
     * el login es la primera impresión de marca, antes de que el visitante haya
     * entrado a ninguno de los dos paneles.
     */
    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->color('success');
    }
}
