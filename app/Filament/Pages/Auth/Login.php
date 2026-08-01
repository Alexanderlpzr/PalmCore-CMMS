<?php

namespace App\Filament\Pages\Auth;

use App\Models\LoginBackgroundImage;
use App\Support\FrondaPalette;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    protected static string $layout = 'filament.pages.auth.login-layout';

    /**
     * El login es la primera impresión de marca, así que usa el verde exacto del
     * logotipo. Antes tenía un tono propio (#2f6b46) que no coincidía ni con el
     * logo ni con el resto del producto: era el quinto verde distinto del proyecto.
     */
    private const BRAND_GREEN = FrondaPalette::LogoGreen;

    /**
     * @return Collection<int, LoginBackgroundImage>
     */
    public function getBackgroundImages(): Collection
    {
        return LoginBackgroundImage::visible()->get();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Entra a tu cuenta';
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->extraInputAttributes(['class' => 'fi-login-field']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->extraInputAttributes(['class' => 'fi-login-field']);
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()
            ->extraInputAttributes(['style' => 'accent-color: '.self::BRAND_GREEN]);
    }

    /**
     * Se pasa la rampa completa en vez de Color::hex(): ese helper conserva solo
     * el tono del color y le aplica la curva de luminosidad de Filament, así que
     * el botón salía de un verde más claro que el del logotipo.
     */
    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->color(FrondaPalette::Brand);
    }
}
