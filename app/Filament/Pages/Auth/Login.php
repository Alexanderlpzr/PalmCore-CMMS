<?php

namespace App\Filament\Pages\Auth;

use App\Models\LoginBackgroundImage;
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
}
