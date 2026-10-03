<?php

namespace App\Filament\Platform\Resources\Users;

use App\Domain\Platform\Services\PlatformUserService;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\ImpersonationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rules\Password;

/**
 * Lo que el superadministrador le puede hacer a una cuenta. Las mismas acciones en la
 * fila del listado y en la ficha del usuario: si vivieran en dos sitios, tarde o
 * temprano una tendría una regla que la otra no.
 *
 * Toda la lógica —reglas, auditoría— está en {@see PlatformUserService}; aquí solo se
 * pregunta, se confirma y se traduce la respuesta a una notificación.
 */
final class UserActions
{
    /** @return list<Action> */
    public static function all(): array
    {
        return [
            self::setPassword(),
            self::temporaryPassword(),
            self::impersonate(),
            self::signOut(),
            self::resetTwoFactor(),
            self::toggleSuperAdmin(),
            self::toggleActive(),
        ];
    }

    /** El superadministrador escribe la contraseña nueva. */
    public static function setPassword(): Action
    {
        return Action::make('setPassword')
            ->label('Cambiar contraseña')
            ->icon(Heroicon::OutlinedKey)
            ->visible(fn (User $record): bool => self::isSomeoneElse($record))
            ->modalHeading(fn (User $record): string => "Cambiar la contraseña de {$record->name}")
            ->modalDescription('Se cerrarán todas sus sesiones abiertas, en la web y en la app móvil.')
            ->modalSubmitActionLabel('Cambiar contraseña')
            ->schema([
                Toggle::make('must_change')
                    ->label('Que la cambie por una propia al entrar')
                    ->helperText('Recomendado: así solo esa persona conocerá su contraseña definitiva.')
                    ->default(true)
                    ->live(),
                TextInput::make('password')
                    ->label('Contraseña nueva')
                    ->password()
                    ->revealable()
                    ->required()
                    // Una temporal solo dura hasta el primer ingreso; la definitiva tiene
                    // que cumplir la política completa.
                    ->rule(fn (Get $get): Password => $get('must_change') ? Password::min(8) : Password::default())
                    ->same('password_confirmation'),
                TextInput::make('password_confirmation')
                    ->label('Repita la contraseña')
                    ->password()
                    ->revealable()
                    ->required()
                    ->dehydrated(false),
            ])
            ->action(function (User $record, array $data): void {
                self::run(
                    fn () => app(PlatformUserService::class)->setPassword($record, $data['password'], (bool) $data['must_change'], self::actor()),
                    'Contraseña cambiada',
                    $data['must_change']
                        ? "{$record->name} tendrá que elegir una propia al entrar."
                        : "{$record->name} ya puede entrar con la contraseña nueva.",
                );
            });
    }

    /** El sistema la genera y la muestra una sola vez. */
    public static function temporaryPassword(): Action
    {
        return Action::make('temporaryPassword')
            ->label('Generar contraseña temporal')
            ->icon(Heroicon::OutlinedSparkles)
            ->visible(fn (User $record): bool => self::isSomeoneElse($record))
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => "Contraseña temporal para {$record->name}")
            ->modalDescription('Se cerrarán sus sesiones abiertas. La contraseña se mostrará una sola vez y tendrá que cambiarla al entrar.')
            ->modalSubmitActionLabel('Generar')
            ->action(function (User $record): void {
                $password = app(PlatformUserService::class)->temporaryPassword($record, self::actor());

                self::showPasswordOnce($record, $password);
            });
    }

    /**
     * Entrar al panel de su empresa como si fuera esa persona, para ver lo que ella ve.
     * Nunca como otro superadministrador, y todo queda en el registro de suplantaciones.
     */
    public static function impersonate(): Action
    {
        return Action::make('impersonate')
            ->label('Entrar como')
            ->icon(Heroicon::OutlinedUserCircle)
            ->color('warning')
            ->visible(fn (User $record): bool => self::isSomeoneElse($record)
                && ! $record->is_super_admin
                && $record->is_active
                && $record->tenants()->exists()
                && ! app(ImpersonationService::class)->isImpersonating())
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => "Entrar como {$record->name}")
            ->modalDescription('Verá el panel exactamente como lo ve esa persona. La sesión queda registrada, con su motivo, en Suplantaciones.')
            ->modalSubmitActionLabel('Entrar')
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo')
                    ->placeholder('Ej.: no le aparece el botón para cerrar OT')
                    ->maxLength(500)
                    ->rows(2),
            ])
            ->action(function (User $record, array $data, Action $action): void {
                try {
                    app(ImpersonationService::class)->start(self::actor(), $record, $data['reason'] ?? null, request());
                } catch (AuthorizationException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                // Carga completa, no navegación parcial: cambia la identidad de la sesión
                // y el panel de destino es otro.
                $action->redirect('/admin', navigate: false);
            });
    }

    /** Saca a la persona de todos sus dispositivos sin tocar su contraseña. */
    public static function signOut(): Action
    {
        return Action::make('signOut')
            ->label('Cerrar sus sesiones')
            ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
            ->visible(fn (User $record): bool => self::isSomeoneElse($record))
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => "Cerrar las sesiones de {$record->name}")
            ->modalDescription('Tendrá que volver a entrar en la web y en la app móvil, con la misma contraseña.')
            ->modalSubmitActionLabel('Cerrar sesiones')
            ->action(fn (User $record) => self::run(
                fn () => app(PlatformUserService::class)->signOutEverywhere($record, self::actor()),
                'Sesiones cerradas',
            ));
    }

    /** Para quien perdió el teléfono con su verificación en dos pasos. */
    public static function resetTwoFactor(): Action
    {
        return Action::make('resetTwoFactor')
            ->label('Quitar verificación en dos pasos')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->color('warning')
            ->visible(fn (User $record): bool => self::isSomeoneElse($record)
                && ($record->two_factor_confirmed_at !== null || $record->passkeys()->exists()))
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => "Quitar la verificación en dos pasos de {$record->name}")
            ->modalDescription('Se borran su código de verificación y sus llaves de acceso, y se cierran sus sesiones. Podrá entrar solo con su contraseña y volver a configurarlas desde su Perfil.')
            ->modalSubmitActionLabel('Quitar')
            ->action(fn (User $record) => self::run(
                fn () => app(PlatformUserService::class)->resetTwoFactor($record, self::actor()),
                'Verificación en dos pasos quitada',
            ));
    }

    /** Nombrar o retirar a otro superadministrador. */
    public static function toggleSuperAdmin(): Action
    {
        return Action::make('toggleSuperAdmin')
            ->label(fn (User $record): string => $record->is_super_admin ? 'Quitar superadministrador' : 'Nombrar superadministrador')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color(fn (User $record): string => $record->is_super_admin ? 'danger' : 'warning')
            ->visible(fn (User $record): bool => self::isSomeoneElse($record)
                && ($record->is_super_admin || $record->is_active))
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => $record->is_super_admin
                ? "Quitar a {$record->name} como superadministrador"
                : "Nombrar a {$record->name} superadministrador")
            ->modalDescription(fn (User $record): string => $record->is_super_admin
                ? 'Deja de ver la plataforma y conserva solo el acceso de su empresa, si tiene.'
                : 'Podrá hacer todo lo que usted hace aquí: ver todas las empresas, cambiar contraseñas, entrar como cualquier usuario y nombrar a otros. Quedará registrado.')
            ->modalSubmitActionLabel(fn (User $record): string => $record->is_super_admin ? 'Quitar' : 'Nombrar')
            ->action(function (User $record): void {
                $service = app(PlatformUserService::class);
                $wasSuperAdmin = $record->is_super_admin;

                self::run(
                    fn () => $wasSuperAdmin
                        ? $service->revokeSuperAdmin($record, self::actor())
                        : $service->grantSuperAdmin($record, self::actor()),
                    $wasSuperAdmin ? 'Ya no es superadministrador' : 'Ahora es superadministrador',
                );
            });
    }

    public static function toggleActive(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (User $record): string => $record->is_active ? 'Desactivar' : 'Activar')
            ->icon(fn (User $record): Heroicon => $record->is_active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
            ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
            ->visible(fn (User $record): bool => self::isSomeoneElse($record))
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => $record->is_active
                ? 'No podrá entrar hasta que se active de nuevo, y se cierran sus sesiones abiertas. Sus datos y su historia se conservan.'
                : 'Podrá volver a entrar con su contraseña actual.')
            ->action(function (User $record): void {
                $activate = ! $record->is_active;

                self::run(
                    fn () => app(PlatformUserService::class)->setActive($record, $activate, self::actor()),
                    $activate ? 'Usuario activado' : 'Usuario desactivado',
                );
            });
    }

    /** Muestra una contraseña recién generada. Es la única vez que existe en claro. */
    public static function showPasswordOnce(User $user, string $password): void
    {
        Notification::make()
            ->title("Contraseña temporal de {$user->name}")
            ->body("**{$password}**\n\nCópiela ahora: no se vuelve a mostrar. Correo: {$user->email}")
            ->success()
            ->persistent()
            ->send();
    }

    /**
     * Las reglas viven en el servicio y hablan español. Aquí solo se traduce la
     * negativa a una notificación en vez de a una pantalla de error.
     */
    public static function run(callable $operation, string $successTitle, ?string $successBody = null): void
    {
        try {
            $operation();
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($successTitle)->body($successBody)->success()->send();
    }

    /** La propia cuenta se gestiona desde el Perfil, no desde aquí. */
    private static function isSomeoneElse(User $record): bool
    {
        return $record->getKey() !== auth()->id();
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
