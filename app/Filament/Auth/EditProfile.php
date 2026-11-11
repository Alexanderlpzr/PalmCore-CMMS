<?php

namespace App\Filament\Auth;

use App\Domain\Platform\Services\UserPasswordService;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;

/**
 * El perfil de cada usuario: nombre, correo y su propia contraseña.
 *
 * Es también la única pantalla que se abre a quien entró con una contraseña temporal
 * ({@see EnsurePasswordIsChanged}). En ese caso la contraseña nueva
 * es obligatoria y no puede ser la misma temporal: sin eso, bastaría con guardar el
 * formulario tal cual para salir del bloqueo sin haber cambiado nada.
 */
class EditProfile extends BaseEditProfile
{
    private bool $passwordChanged = false;

    public function getHeading(): string|Htmlable
    {
        return $this->mustChangePassword() ? 'Elija su contraseña' : parent::getHeading();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->mustChangePassword()
            ? 'Entró con una contraseña temporal. Escriba una nueva para empezar a usar Fronda.'
            : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            // Sin «contraseña actual»: solo la nueva y su confirmación (pedido 2026-11-10).
        ]);
    }

    /** Siempre a la vista, al lado de la nueva; se exige en cuanto se escribe una. */
    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->label('Confirmar contraseña nueva')
            ->visible(true)
            ->required(fn (Get $get): bool => filled($get('password')) || $this->mustChangePassword());
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Contraseña nueva')
            ->required(fn (): bool => $this->mustChangePassword())
            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && Hash::check($value, $this->getUser()->getAuthPassword())) {
                    $fail('La contraseña nueva tiene que ser distinta de la actual.');
                }
            });
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->passwordChanged = array_key_exists('password', $data);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->passwordChanged) {
            app(UserPasswordService::class)->markChangedByOwner($this->getUser());
        }
    }

    /** Ya con su contraseña, vuelve al panel en vez de quedarse en el Perfil. */
    protected function getRedirectUrl(): ?string
    {
        return $this->passwordChanged ? Filament::getUrl() : null;
    }

    private function mustChangePassword(): bool
    {
        /** @var User $user */
        $user = $this->getUser();

        return (bool) $user->must_change_password;
    }
}
