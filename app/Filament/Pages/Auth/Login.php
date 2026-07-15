<?php

namespace App\Filament\Pages\Auth;

use App\Models\LoginBackgroundImage;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    protected static string $layout = 'filament.pages.auth.login-layout';

    /**
     * El verde de este rediseño (handoff LOGIN-2): un tono propio de esta pantalla,
     * no el Emerald que usa el resto del producto — es la primera impresión de marca,
     * antes de que el visitante haya entrado a ningún panel.
     */
    private const BRAND_GREEN = '#2f6b46';

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

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->color(Color::hex(self::BRAND_GREEN));
    }
}
